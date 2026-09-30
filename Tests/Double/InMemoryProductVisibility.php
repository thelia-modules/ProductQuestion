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

namespace ProductQuestion\Tests\Double;

use ProductQuestion\Repository\ProductVisibilityInterface;

final class InMemoryProductVisibility implements ProductVisibilityInterface
{
    /**
     * @param list<int> $visibleIds
     */
    public function __construct(private readonly array $visibleIds = [12])
    {
    }

    public function isVisible(int $productId): bool
    {
        return \in_array($productId, $this->visibleIds, true);
    }
}
