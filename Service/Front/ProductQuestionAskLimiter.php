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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * How many questions one customer may ask.
 *
 * Asking costs a row, a line in the moderation queue and, from the next phase on, a mail to
 * the shop. Two budgets are spent on every attempt: one for the customer, one for the
 * customer on that product, so that a script cannot fill the queue about one product and a
 * heavy hand cannot fill it about the whole catalogue.
 *
 * Keyed on the account rather than the address: only a signed-in customer reaches this, and
 * an account is what a moderator sees and can act on.
 *
 * The limiters are declared by ProductQuestion::configureContainer().
 */
final readonly class ProductQuestionAskLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.product_question_ask_per_customer')]
        private RateLimiterFactoryInterface $perCustomerLimiter,
        #[Autowire(service: 'limiter.product_question_ask_per_product')]
        private RateLimiterFactoryInterface $perProductLimiter,
    ) {
    }

    public function allows(int $customerId, int $productId): bool
    {
        if (!$this->perCustomerLimiter->create((string) $customerId)->consume()->isAccepted()) {
            return false;
        }

        return $this->perProductLimiter
            ->create($customerId.'|'.$productId)
            ->consume()
            ->isAccepted();
    }
}
