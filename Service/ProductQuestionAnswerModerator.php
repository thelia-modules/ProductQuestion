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
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ProductQuestionAnswerStorageInterface;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * What a moderator does with one answer: publish it, refuse it, delete it.
 *
 * Each of them changes what the product page counts, so each recounts the question's helpful
 * votes. Publishing a customer's answer for the first time tells the author of the question,
 * through the same event as the shop's own answer.
 */
final readonly class ProductQuestionAnswerModerator
{
    public function __construct(
        private ProductQuestionStorageInterface $storage,
        private ProductQuestionAnswerStorageInterface $answers,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function publish(ProductQuestionAnswer $answer): void
    {
        $firstPublication = null === $answer->getPublishedAt();

        $answer->setStatusEnum(ProductQuestionStatus::Published);

        if ($firstPublication) {
            $answer->setPublishedAt(new \DateTimeImmutable());
        }

        $this->answers->save($answer);

        $questionId = (int) $answer->getQuestionId();
        $this->answers->refreshQuestionHelpfulCount($questionId);

        $question = $this->storage->findById($questionId);

        if (null !== $question) {
            $this->dispatcher->dispatch(new ProductQuestionAnsweredEvent($question, $firstPublication, $answer));
        }
    }

    public function refuse(ProductQuestionAnswer $answer): void
    {
        $answer->setStatusEnum(ProductQuestionStatus::Refused);

        $this->answers->save($answer);
        $this->answers->refreshQuestionHelpfulCount((int) $answer->getQuestionId());
    }

    public function delete(ProductQuestionAnswer $answer): void
    {
        $questionId = (int) $answer->getQuestionId();

        // Its votes go with it, by the foreign key.
        $this->answers->delete($answer);
        $this->answers->refreshQuestionHelpfulCount($questionId);
    }
}
