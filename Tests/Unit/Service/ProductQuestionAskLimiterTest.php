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

namespace ProductQuestion\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use ProductQuestion\Service\Front\ProductQuestionAskLimiter;
use ProductQuestion\Tests\Double\RateLimiters;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ProductQuestionAskLimiterTest extends TestCase
{
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->from('203.0.113.7');
    }

    private function from(string $ip): void
    {
        while (null !== $this->requestStack->pop()) {
        }

        $this->requestStack->push(Request::create('/', 'POST', server: ['REMOTE_ADDR' => $ip]));
    }

    private function limiter(int $perCustomer, int $perProduct, int $perIp = 100): ProductQuestionAskLimiter
    {
        return new ProductQuestionAskLimiter(
            RateLimiters::slidingWindow('per_customer', $perCustomer),
            RateLimiters::slidingWindow('per_product', $perProduct),
            RateLimiters::slidingWindow('per_ip', $perIp),
            $this->requestStack,
        );
    }

    /**
     * The per-product budget: the third question about one product is the last one.
     */
    public function testACustomerCannotFloodOneProduct(): void
    {
        $limiter = $this->limiter(perCustomer: 10, perProduct: 2);

        self::assertTrue($limiter->allows(1, 12));
        self::assertTrue($limiter->allows(1, 12));
        self::assertFalse($limiter->allows(1, 12));

        // Another product of the same customer has a budget of its own.
        self::assertTrue($limiter->allows(1, 13));
    }

    /**
     * The per-customer budget: across every product, one account has a ceiling too.
     */
    public function testACustomerCannotFloodTheCatalogue(): void
    {
        $limiter = $this->limiter(perCustomer: 3, perProduct: 10);

        self::assertTrue($limiter->allows(1, 1));
        self::assertTrue($limiter->allows(1, 2));
        self::assertTrue($limiter->allows(1, 3));
        self::assertFalse($limiter->allows(1, 4));

        // Another customer is unaffected.
        self::assertTrue($limiter->allows(2, 4));
    }

    /**
     * Registration is open, so the account budgets alone let N accounts send N times as many
     * questions, and as many mails to the shop. The address caps them together.
     */
    public function testAccountsSharingAnAddressShareItsBudget(): void
    {
        $limiter = $this->limiter(perCustomer: 10, perProduct: 10, perIp: 2);

        self::assertTrue($limiter->allows(1, 12));
        self::assertTrue($limiter->allows(2, 12));
        self::assertFalse($limiter->allows(3, 12));

        // Another address has a budget of its own.
        $this->from('198.51.100.4');
        self::assertTrue($limiter->allows(3, 12));
    }
}
