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
use ProductQuestion\Service\ProductQuestionAvailability;
use ProductQuestion\Tests\Double\FixedSettings;
use ProductQuestion\Tests\Double\InMemoryClosedProducts;

final class ProductQuestionAvailabilityTest extends TestCase
{
    public function testAProductIsOpenUnlessTheShopOrTheProductIsClosed(): void
    {
        $availability = new ProductQuestionAvailability(new FixedSettings(), new InMemoryClosedProducts([99]));

        self::assertTrue($availability->isOpenFor(12));
        self::assertFalse($availability->isOpenFor(99));
    }

    /**
     * The shop-wide setting wins, and spares the query on the product.
     */
    public function testClosingTheShopClosesEveryProductWithoutReadingThem(): void
    {
        $closedProducts = new InMemoryClosedProducts();
        $availability = new ProductQuestionAvailability(new FixedSettings(closed: true), $closedProducts);

        self::assertFalse($availability->isOpenFor(12));
        self::assertSame(0, $closedProducts->reads);
    }
}
