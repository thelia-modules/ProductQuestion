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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * How many questions one customer may ask.
 *
 * Asking costs a row, a line in the moderation queue and a mail to the shop. Three budgets
 * are spent on every attempt: one for the address the request comes from, one for the
 * customer, one for the customer on that product. The account budgets are what a moderator
 * sees and can act on; the address one is there because registration is open, and N accounts
 * would otherwise send N times as many questions and mails.
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
        #[Autowire(service: 'limiter.product_question_ask_per_ip')]
        private RateLimiterFactoryInterface $perIpLimiter,
        private RequestStack $requestStack,
    ) {
    }

    public function allows(int $customerId, int $productId): bool
    {
        $client = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';

        if (!$this->perIpLimiter->create($client)->consume()->isAccepted()) {
            return false;
        }

        if (!$this->perCustomerLimiter->create((string) $customerId)->consume()->isAccepted()) {
            return false;
        }

        return $this->perProductLimiter
            ->create($customerId.'|'.$productId)
            ->consume()
            ->isAccepted();
    }
}
