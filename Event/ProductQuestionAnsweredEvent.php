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

namespace ProductQuestion\Event;

use ProductQuestion\Model\ProductQuestion;

/**
 * The shop has written or rewritten its answer.
 *
 * `firstAnswer` tells the two apart: the customer is told once, when their question goes on
 * the page, not every time a moderator fixes a typo.
 */
final class ProductQuestionAnsweredEvent extends ProductQuestionEvent
{
    public function __construct(
        ProductQuestion $question,
        private readonly bool $firstAnswer = true,
    ) {
        parent::__construct($question);
    }

    public function isFirstAnswer(): bool
    {
        return $this->firstAnswer;
    }
}
