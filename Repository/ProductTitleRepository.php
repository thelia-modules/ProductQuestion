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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\ProductI18nQuery;

/**
 * Product titles, read in one query for a whole page of questions.
 *
 * A question names a product by its id; the back office names it by its title. Resolving that
 * per row would be one query per line of the list, which is why this takes a set.
 */
final readonly class ProductTitleRepository implements ProductTitleSourceInterface
{
    /**
     * @param list<int> $productIds
     *
     * @return array<int, string>
     */
    public function titlesFor(array $productIds, string $locale): array
    {
        if ([] === $productIds) {
            return [];
        }

        $titles = [];

        $rows = ProductI18nQuery::create()
            ->filterById(array_values(array_unique($productIds)), Criteria::IN)
            ->filterByLocale($locale)
            ->find();

        foreach ($rows as $row) {
            $titles[(int) $row->getId()] = (string) $row->getTitle();
        }

        return $titles;
    }

    public function titleFor(int $productId, string $locale): ?string
    {
        return $this->titlesFor([$productId], $locale)[$productId] ?? null;
    }
}
