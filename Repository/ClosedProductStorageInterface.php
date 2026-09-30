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
 * The products the shop closed to new questions, one by one.
 *
 * Behind a contract so that the rules which depend on it run in a unit test without a
 * database.
 */
interface ClosedProductStorageInterface
{
    public function isClosed(int $productId): bool;

    public function setClosed(int $productId, bool $closed): void;
}
