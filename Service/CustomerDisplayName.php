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

namespace ProductQuestion\Service;

use ProductQuestion\Model\ProductQuestion;

/**
 * How the module names the customer who asked: first and last name, the e-mail address when
 * the account carries no name, nothing once the account is gone.
 *
 * One rule for the moderation list and the shop's mail, so that changing it changes both.
 */
final class CustomerDisplayName
{
    public static function of(ProductQuestion $question): ?string
    {
        $customer = $question->getCustomer();

        if (null === $customer) {
            return null;
        }

        $name = trim(($customer->getFirstname() ?? '').' '.($customer->getLastname() ?? ''));

        return '' === $name ? $customer->getEmail() : $name;
    }
}
