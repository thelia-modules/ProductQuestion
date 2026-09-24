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

use ProductQuestion\Service\Notification\CustomerMailerInterface;

final class SpyCustomerMailer implements CustomerMailerInterface
{
    /** @var list<array{code: string, customerId: int, locale: string, parameters: array<string, mixed>}> */
    public array $sent = [];

    public function __construct(private ?\Throwable $failure = null)
    {
    }

    public function sendToCustomer(string $messageCode, int $customerId, string $locale, array $parameters): void
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $this->sent[] = ['code' => $messageCode, 'customerId' => $customerId, 'locale' => $locale, 'parameters' => $parameters];
    }
}
