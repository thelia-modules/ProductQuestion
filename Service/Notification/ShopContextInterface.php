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

namespace ProductQuestion\Service\Notification;

/**
 * What a notification needs to know about the shop and that only a booted Thelia knows: its
 * language, the absolute URL of a back-office screen, the public URL of a product page.
 */
interface ShopContextInterface
{
    public function defaultLocale(): string;

    public function adminUrlOfQuestion(int $questionId): string;

    public function productUrl(int $productId, string $locale): string;
}
