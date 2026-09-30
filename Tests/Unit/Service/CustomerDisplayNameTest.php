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

namespace ProductQuestion\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Service\CustomerDisplayName;
use ProductQuestion\Tests\Double\CustomerStandIn;

final class CustomerDisplayNameTest extends TestCase
{
    private function askedBy(?object $customer): ProductQuestion
    {
        return (new ProductQuestion())->setCustomer($customer);
    }

    private function customer(?string $first, ?string $last, string $email = 'ada@example.com'): object
    {
        return CustomerStandIn::make($first, $last, $email);
    }

    public function testACustomerIsNamedByTheirFullName(): void
    {
        self::assertSame('Ada Lovelace', CustomerDisplayName::of($this->askedBy($this->customer('Ada', 'Lovelace'))));
        self::assertSame('Lovelace', CustomerDisplayName::of($this->askedBy($this->customer(null, 'Lovelace'))));
    }

    public function testAnAccountWithNoNameIsNamedByItsEmail(): void
    {
        self::assertSame('ada@example.com', CustomerDisplayName::of($this->askedBy($this->customer(null, ' '))));
    }

    public function testAClosedAccountHasNoName(): void
    {
        self::assertNull(CustomerDisplayName::of($this->askedBy(null)));
    }
}
