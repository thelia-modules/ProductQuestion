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

final class ProductQuestionAskLimiterTest extends TestCase
{
    private function limiter(int $perCustomer, int $perProduct): ProductQuestionAskLimiter
    {
        return new ProductQuestionAskLimiter(
            RateLimiters::slidingWindow('per_customer', $perCustomer),
            RateLimiters::slidingWindow('per_product', $perProduct),
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
}
