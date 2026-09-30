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

use ProductQuestion\Service\Front\CurrentCustomerInterface;

final class FixedCurrentCustomer implements CurrentCustomerInterface
{
    public function __construct(private ?int $id)
    {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
