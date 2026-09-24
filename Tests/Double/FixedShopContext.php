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

use ProductQuestion\Service\Notification\ShopContextInterface;

final class FixedShopContext implements ShopContextInterface
{
    public function __construct(private string $locale = 'fr_FR')
    {
    }

    public function defaultLocale(): string
    {
        return $this->locale;
    }

    public function adminUrlOfQuestion(int $questionId): string
    {
        return 'https://shop.test/admin/module/ProductQuestion/'.$questionId;
    }

    public function productUrl(int $productId, string $locale): string
    {
        return 'https://shop.test/'.$locale.'/product-'.$productId.'.html';
    }
}
