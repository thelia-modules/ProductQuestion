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

/**
 * How the back office turns the product id a question carries into a title a moderator reads.
 *
 * Behind a contract for the same reason the questions are: the presenter that uses it is
 * where the list takes its shape, and that has to be exercisable without a database.
 */
interface ProductTitleSourceInterface
{
    /**
     * @param list<int> $productIds
     *
     * @return array<int, string>
     */
    public function titlesFor(array $productIds, string $locale): array;

    public function titleFor(int $productId, string $locale): ?string;
}
