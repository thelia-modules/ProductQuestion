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
use Symfony\Contracts\EventDispatcher\Event;

/**
 * What the three events of this module carry: the question the service has just written.
 *
 * They are dispatched after the row is saved, so a listener reads a persisted question and
 * cannot cancel the decision. Anything that has to run before belongs in the service.
 */
abstract class ProductQuestionEvent extends Event
{
    public function __construct(
        private readonly ProductQuestion $question,
    ) {
    }

    public function getQuestion(): ProductQuestion
    {
        return $this->question;
    }
}
