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

use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;

/**
 * Not sendEmailToCustomer(): that one writes in the language of the account, and the answer
 * a customer is told about was written in the language they asked in.
 */
final readonly class TheliaCustomerMailer implements CustomerMailerInterface
{
    public function __construct(
        private MailerFactory $mailer,
    ) {
    }

    public function sendToCustomer(string $messageCode, int $customerId, string $locale, array $parameters): void
    {
        $customer = CustomerQuery::create()->findPk($customerId);

        // The account went away between the question and the answer: nobody to write to.
        if (null === $customer || '' === (string) $customer->getEmail()) {
            return;
        }

        $this->mailer->sendEmailMessageOrFail(
            $messageCode,
            [ConfigQuery::getStoreEmail() => ConfigQuery::getStoreName()],
            [$customer->getEmail() => trim($customer->getFirstname().' '.$customer->getLastname())],
            $parameters,
            $locale,
        );
    }
}
