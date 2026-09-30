<?php

declare(strict_types=1);

/*
 * This file is part of the ProductQuestion module for Thelia 3.
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ProductQuestion\Service;

use ProductQuestion\Event\ProductQuestionAnsweredEvent;
use ProductQuestion\Exception\InvalidProductQuestionException;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ProductQuestionAnswerStorageInterface;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Service\Front\ProductQuestionTextSanitizer;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Publishes the shop's own answer to a question: the official one, shown first.
 *
 * A question has one official answer. Writing it again rewrites that answer rather than adding
 * a second one. The shop is the moderator, so its answer is published as it is written, and
 * publishing it puts the question on the product page as well.
 */
final readonly class ProductQuestionAnswerer
{
    public const MAXIMUM_LENGTH = 5000;

    public function __construct(
        private ProductQuestionStorageInterface $storage,
        private ProductQuestionAnswerStorageInterface $answers,
        private ProductQuestionTextSanitizer $sanitizer,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function answer(ProductQuestion $question, ?string $answer, int $adminId): ProductQuestionAnswer
    {
        $clean = $this->sanitizer->sanitize($answer);

        if ('' === $clean) {
            throw InvalidProductQuestionException::emptyAnswer();
        }

        if (mb_strlen($clean) > self::MAXIMUM_LENGTH) {
            throw InvalidProductQuestionException::answerTooLong(self::MAXIMUM_LENGTH);
        }

        $questionId = (int) $question->getId();
        $official = $this->answers->findOfficialForQuestion($questionId) ?? (new ProductQuestionAnswer())
            ->setQuestionId($questionId)
            ->setIsOfficial(true);

        // Read before anything is written: the date of the first publication, not the status,
        // tells a first answer from an edit. A refused question keeps its answer, so one refused
        // and published again has already been announced to its author.
        $firstPublication = null === $official->getPublishedAt();

        $official
            ->setContent($clean)
            // Nullable, and set to null when the account goes: an answer written by an
            // administrator who has left stays on the page without an author.
            ->setAdminId($adminId > 0 ? $adminId : null)
            ->setStatusEnum(ProductQuestionStatus::Published);

        if ($firstPublication) {
            $official->setPublishedAt(new \DateTimeImmutable());
        }

        $this->answers->save($official);

        if (!$question->isPublished()) {
            $question->setStatusEnum(ProductQuestionStatus::Published);
            $this->storage->save($question);
        }

        $this->answers->refreshQuestionHelpfulCount($questionId);

        $this->dispatcher->dispatch(new ProductQuestionAnsweredEvent($question, $firstPublication, $official));

        return $official;
    }
}
