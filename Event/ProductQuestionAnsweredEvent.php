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
use ProductQuestion\Model\ProductQuestionAnswer;

/**
 * An answer to the question has just been published: the shop's own, written or rewritten, or
 * a customer's accepted by a moderator.
 *
 * `firstAnswer` tells a first publication from a later one: the author of the question is told
 * once per answer, when it goes on the page, not every time a moderator fixes a typo.
 */
final class ProductQuestionAnsweredEvent extends ProductQuestionEvent
{
    public function __construct(
        ProductQuestion $question,
        private readonly bool $firstAnswer = true,
        private readonly ?ProductQuestionAnswer $answer = null,
    ) {
        parent::__construct($question);
    }

    public function isFirstAnswer(): bool
    {
        return $this->firstAnswer;
    }

    /** Null only for a listener dispatching the event the 1.2.0 way, with no answer row. */
    public function getAnswer(): ?ProductQuestionAnswer
    {
        return $this->answer;
    }
}
