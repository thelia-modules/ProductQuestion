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

namespace ProductQuestion\Repository;

use Thelia\Model\ProductQuery;

final readonly class ProductVisibilityRepository implements ProductVisibilityInterface
{
    public function isVisible(int $productId): bool
    {
        return ProductQuery::create()
            ->filterById($productId)
            ->filterByVisible(1)
            ->exists();
    }
}
