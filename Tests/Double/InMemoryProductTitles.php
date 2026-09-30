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

use ProductQuestion\Repository\ProductTitleSourceInterface;

/**
 * Product titles held in an array, keyed by locale then by product id.
 */
final class InMemoryProductTitles implements ProductTitleSourceInterface
{
    /** @var list<array{ids: list<int>, locale: string}> */
    public array $calls = [];

    /**
     * @param array<string, array<int, string>> $titles
     */
    public function __construct(private readonly array $titles = [])
    {
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, string>
     */
    public function titlesFor(array $productIds, string $locale): array
    {
        $this->calls[] = ['ids' => $productIds, 'locale' => $locale];

        $known = $this->titles[$locale] ?? [];

        return array_intersect_key($known, array_flip($productIds));
    }

    public function titleFor(int $productId, string $locale): ?string
    {
        return $this->titlesFor([$productId], $locale)[$productId] ?? null;
    }
}
