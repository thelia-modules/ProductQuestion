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

namespace ProductQuestion\Model;

use ProductQuestion\Model\Base\ProductQuestion as BaseProductQuestion;

class ProductQuestion extends BaseProductQuestion
{
    /**
     * The stored status as the enum, or null when the column holds a value no version of
     * this module ever wrote. Callers decide what to do with that; nothing here pretends
     * an unknown row is pending.
     */
    public function getStatusEnum(): ?ProductQuestionStatus
    {
        $status = $this->getStatus();

        return null === $status ? null : ProductQuestionStatus::tryFrom($status);
    }

    /**
     * The generated setter takes the TINYINT as ?int and rejects anything else under
     * strict_types. Going through the enum is what keeps that cast in one place.
     */
    public function setStatusEnum(ProductQuestionStatus $status): static
    {
        return $this->setStatus($status->value);
    }

    public function isAnswered(): bool
    {
        return ProductQuestionStatus::Answered === $this->getStatusEnum();
    }
}
