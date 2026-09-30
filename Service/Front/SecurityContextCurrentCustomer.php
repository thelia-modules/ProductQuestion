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

namespace ProductQuestion\Service\Front;

use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Customer;

/**
 * The customer signed in to the shop, as the front-office session knows them.
 *
 * SecurityContext answers a Customer for a signed-in account and for a guest checkout alike;
 * a guest row has no account behind it and never gets to ask, so it answers null here.
 */
final readonly class SecurityContextCurrentCustomer implements CurrentCustomerInterface
{
    public function __construct(
        private SecurityContext $securityContext,
    ) {
    }

    public function id(): ?int
    {
        $customer = $this->securityContext->getCustomerUser();

        if (!$customer instanceof Customer || $customer->isGuest()) {
            return null;
        }

        $id = (int) $customer->getId();

        return $id > 0 ? $id : null;
    }
}
