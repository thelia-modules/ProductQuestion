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

namespace ProductQuestion\Tests\Double;

use ProductQuestion\Service\ProductQuestionSettingsInterface;

final class FixedSettings implements ProductQuestionSettingsInterface
{
    public function __construct(
        public bool $customerAnswers = false,
        public bool $closed = false,
    ) {
    }

    public function allowsCustomerAnswers(): bool
    {
        return $this->customerAnswers;
    }

    public function setAllowsCustomerAnswers(bool $allowed): void
    {
        $this->customerAnswers = $allowed;
    }

    public function questionsClosed(): bool
    {
        return $this->closed;
    }

    public function setQuestionsClosed(bool $closed): void
    {
        $this->closed = $closed;
    }
}
