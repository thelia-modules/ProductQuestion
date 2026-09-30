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
 * How many answers one customer may write, on the same terms as ProductQuestionAskLimiter: a
 * budget per account, and one per address because registration is open.
 *
 * The limiters are declared by ProductQuestion::configureContainer().
 */
final readonly class ProductQuestionAnswerLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.product_question_answer_per_customer')]
        private RateLimiterFactoryInterface $perCustomerLimiter,
        #[Autowire(service: 'limiter.product_question_answer_per_ip')]
        private RateLimiterFactoryInterface $perIpLimiter,
        private RequestStack $requestStack,
    ) {
    }

    public function allows(int $customerId): bool
    {
        $client = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';

        if (!$this->perIpLimiter->create($client)->consume()->isAccepted()) {
            return false;
        }

        return $this->perCustomerLimiter->create((string) $customerId)->consume()->isAccepted();
    }
}
