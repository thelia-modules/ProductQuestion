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

use ProductQuestion\ProductQuestion as ProductQuestionModule;
use Thelia\Model\Lang;
use Thelia\Tools\URL;

final readonly class TheliaShopContext implements ShopContextInterface
{
    public function defaultLocale(): string
    {
        return Lang::getDefaultLanguage()->getLocale() ?? 'en_US';
    }

    public function adminUrlOfQuestion(int $questionId): string
    {
        return URL::getInstance()->absoluteUrl(ProductQuestionModule::ADMIN_LIST_PATH.'/'.$questionId);
    }

    public function productUrl(int $productId, string $locale): string
    {
        // The rewritten URL in that language when the shop has one, the plain view URL otherwise.
        return URL::getInstance()->retrieve('product', $productId, $locale)->toString();
    }
}
