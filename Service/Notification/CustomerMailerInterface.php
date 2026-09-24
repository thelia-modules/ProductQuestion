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

namespace ProductQuestion\Service\Notification;

/**
 * Sends one of the module's messages to one customer, in one language.
 *
 * Behind a contract because reaching the customer means loading their row and going through
 * the shop's mailer, neither of which a unit test has.
 */
interface CustomerMailerInterface
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function sendToCustomer(string $messageCode, int $customerId, string $locale, array $parameters): void;
}
