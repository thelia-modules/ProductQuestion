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

namespace ProductQuestion\Api\State;

use ProductQuestion\Service\Front\CurrentCustomerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Model\Customer;

/**
 * The customer an API request is made by, read off the security token.
 *
 * A JWT minted for a guest checkout carries ROLE_GUEST and a Customer row with no account
 * behind it; such a row never gets to ask, so it answers null here as it does in the shop.
 */
final readonly class TokenCurrentCustomer implements CurrentCustomerInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function id(): ?int
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof Customer || $user->isGuest()) {
            return null;
        }

        $id = (int) $user->getId();

        return $id > 0 ? $id : null;
    }
}
