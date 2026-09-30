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

use Thelia\Model\Customer;

/**
 * A customer for the tests that only read its name and address.
 *
 * Run through the module's own configuration, the Propel base of Thelia\Model\Customer does not
 * exist and a plain object stands in, which the stand-in question base accepts. Run through the
 * root configuration of an install, the generated question base insists on a real Customer, which
 * then exists: the same tests pass either way.
 */
final class CustomerStandIn
{
    public static function make(?string $first, ?string $last, string $email = 'ada@example.com'): object
    {
        if (class_exists('Thelia\Model\Base\Customer')) {
            return (new Customer())->setFirstname($first)->setLastname($last)->setEmail($email);
        }

        return new class($first, $last, $email) {
            public function __construct(private ?string $first, private ?string $last, private string $email)
            {
            }

            public function getFirstname(): ?string
            {
                return $this->first;
            }

            public function getLastname(): ?string
            {
                return $this->last;
            }

            public function getEmail(): string
            {
                return $this->email;
            }
        };
    }
}
