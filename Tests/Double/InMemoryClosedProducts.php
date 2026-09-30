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

use ProductQuestion\Repository\ClosedProductStorageInterface;

final class InMemoryClosedProducts implements ClosedProductStorageInterface
{
    public int $reads = 0;

    /**
     * @param list<int> $closedIds
     */
    public function __construct(private array $closedIds = [])
    {
    }

    public function isClosed(int $productId): bool
    {
        ++$this->reads;

        return \in_array($productId, $this->closedIds, true);
    }

    public function setClosed(int $productId, bool $closed): void
    {
        $this->closedIds = array_values(array_diff($this->closedIds, [$productId]));

        if ($closed) {
            $this->closedIds[] = $productId;
        }
    }
}
