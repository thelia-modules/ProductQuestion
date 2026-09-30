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

use ProductQuestion\Exception\InvalidProductQuestionException;
use ProductQuestion\Repository\ProductQuestionAnswerStorageInterface;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Repository\ProductVisibilityInterface;

/**
 * Counts a customer finding an answer helpful: what orders the answers and the questions of the
 * product page.
 *
 * Once per customer and answer, held by the database. Only on an answer a visitor can read, and
 * never on one's own answer: either would be a way to push a text up the page.
 */
final readonly class ProductQuestionHelpfulVoter
{
    public function __construct(
        private ProductQuestionStorageInterface $storage,
        private ProductQuestionAnswerStorageInterface $answers,
        private ProductVisibilityInterface $products,
    ) {
    }

    /**
     * Returns false when this customer's vote was already counted.
     */
    public function vote(int $answerId, int $customerId): bool
    {
        if ($customerId <= 0) {
            throw InvalidProductQuestionException::unknownCustomer();
        }

        $answer = $answerId > 0 ? $this->answers->findById($answerId) : null;
        $question = null === $answer ? null : $this->storage->findById((int) $answer->getQuestionId());

        if (null === $answer
            || null === $question
            || !$answer->isPublished()
            || !$question->isPublished()
            || !$this->products->isVisible((int) $question->getProductId())) {
            throw InvalidProductQuestionException::unknownAnswer();
        }

        if ($answer->getCustomerId() === $customerId) {
            throw InvalidProductQuestionException::ownAnswer();
        }

        return $this->answers->addVote($answerId, $customerId);
    }
}
