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
 * Whether a product is one a visitor can see, and so one a customer can ask about.
 *
 * Behind a contract so that ProductQuestionAsker keeps running without a database.
 */
interface ProductVisibilityInterface
{
    public function isVisible(int $productId): bool;
}
