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
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Service\Front\ProductQuestionTextSanitizer;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Publishes an administrator's answer.
 *
 * Answering is what puts a question on the product page, so the four columns that say so —
 * the answer, when it was written, who wrote it, and the status — are written together. A
 * later edit of the same answer goes through here too and moves the question back up the
 * product page.
 */
final readonly class ProductQuestionAnswerer
{
    public const MAXIMUM_LENGTH = 5000;

    public function __construct(
        private ProductQuestionStorageInterface $storage,
        private ProductQuestionTextSanitizer $sanitizer,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function answer(ProductQuestion $question, ?string $answer, int $adminId): ProductQuestion
    {
        $clean = $this->sanitizer->sanitize($answer);

        if ('' === $clean) {
            throw InvalidProductQuestionException::emptyAnswer();
        }

        if (mb_strlen($clean) > self::MAXIMUM_LENGTH) {
            throw InvalidProductQuestionException::answerTooLong(self::MAXIMUM_LENGTH);
        }

        // Read before the status moves: this is what tells a first publication from an edit.
        $firstAnswer = !$question->isAnswered();

        $question
            ->setAnswer($clean)
            ->setAnsweredAt(new \DateTimeImmutable())
            // The column is nullable and set to null when the account goes: an answer written
            // by an administrator who has left stays on the page without an author.
            ->setAnsweredBy($adminId > 0 ? $adminId : null)
            ->setStatusEnum(ProductQuestionStatus::Answered);

        $this->storage->save($question);

        $this->dispatcher->dispatch(new ProductQuestionAnsweredEvent($question, $firstAnswer));

        return $question;
    }
}
