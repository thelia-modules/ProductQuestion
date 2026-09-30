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

use ProductQuestion\Model\ProductQuestionClosedProduct;
use ProductQuestion\Model\ProductQuestionClosedProductQuery;
use Thelia\Model\ProductQuery;

/**
 * A row of product_question_closed_product is a closed product; opening it again deletes the row.
 */
final readonly class ClosedProductRepository implements ClosedProductStorageInterface
{
    public function isClosed(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        return ProductQuestionClosedProductQuery::create()
            ->filterByProductId($productId)
            ->exists();
    }

    public function setClosed(int $productId, bool $closed): void
    {
        if ($productId <= 0) {
            return;
        }

        if (!$closed) {
            ProductQuestionClosedProductQuery::create()
                ->filterByProductId($productId)
                ->delete();

            return;
        }

        // Already closed, or no such product: the foreign key would turn the insert into a 500.
        if ($this->isClosed($productId) || !ProductQuery::create()->filterById($productId)->exists()) {
            return;
        }

        (new ProductQuestionClosedProduct())
            ->setProductId($productId)
            ->save();
    }
}
