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

namespace ProductQuestion\Service\Api;

use ProductQuestion\Service\Notification\ShopContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The language of an API request that names none.
 *
 * An API request has no shop session, so its locale is the framework's default ("en"), which
 * is no language of the shop: a question stored in it shows on no product page, and a list
 * read in it is empty. The request's locale is kept when the shop has that language, the
 * shop's default language is used otherwise.
 */
final readonly class ProductQuestionLocaleResolver
{
    public function __construct(
        private RequestStack $requestStack,
        private ShopContextInterface $shop,
    ) {
    }

    public function fallback(): string
    {
        $locale = (string) $this->requestStack->getCurrentRequest()?->getLocale();

        if ('' !== $locale && $this->shop->hasLanguage($locale)) {
            return $locale;
        }

        return $this->shop->defaultLocale();
    }

    public function isShopLanguage(string $locale): bool
    {
        return $this->shop->hasLanguage($locale);
    }
}
