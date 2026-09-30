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

namespace ProductQuestion\Service;

use ProductQuestion\Repository\ClosedProductStorageInterface;

/**
 * Whether a product takes new questions and answers: neither the whole shop nor the product
 * itself is closed.
 *
 * Closing stops what customers write, never what they read: the published questions of a
 * closed product stay on its page and in the API.
 */
final readonly class ProductQuestionAvailability
{
    public function __construct(
        private ProductQuestionSettingsInterface $settings,
        private ClosedProductStorageInterface $closedProducts,
    ) {
    }

    public function isOpenFor(int $productId): bool
    {
        // The shop-wide setting first: it is read from the module configuration the request has
        // already loaded, and spares the query on the product.
        if ($this->settings->questionsClosed()) {
            return false;
        }

        return !$this->closedProducts->isClosed($productId);
    }
}
