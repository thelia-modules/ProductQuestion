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

namespace ProductQuestion\Service\BackOffice;

use ProductQuestion\Repository\ProductQuestionAnswerStorageInterface;
use ProductQuestion\Repository\ProductQuestionStorageInterface;

/**
 * What waits for a moderator across the shop: the pending questions and the pending customer
 * answers, the number the back-office menu shows next to the module's entry.
 *
 * Two indexed counts, read only when asked: the menu hook asks while it renders, so a back-office
 * response without the menu never runs them.
 */
final readonly class PendingModerationCount
{
    public function __construct(
        private ProductQuestionStorageInterface $questions,
        private ProductQuestionAnswerStorageInterface $answers,
    ) {
    }

    public function total(): int
    {
        return $this->questions->countPending() + $this->answers->countPending();
    }
}
