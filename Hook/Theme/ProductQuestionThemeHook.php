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

namespace ProductQuestion\Hook\Theme;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

/**
 * The questions block at the bottom of a product page.
 *
 * The theme declares the point with theme_hook('product.bottom', {product: product}); this
 * is collected through the autoconfigured thelia.theme_hook tag, which is a different
 * mechanism from the back-office hooks and needs no row in the hook table.
 *
 * The hook itself only links the module's stylesheet and mounts the ProductQuestion live
 * component with the product and the language being browsed. Whether there is anything to
 * read, and whether the visitor may ask, is the component's business: a product nobody has
 * asked about still carries the form for a signed-in customer.
 */
final readonly class ProductQuestionThemeHook implements ThemeHookInterface
{
    private const HOOK_NAME = 'product.bottom';

    public function __construct(
        private Environment $twig,
        private RequestStack $requestStack,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return self::HOOK_NAME === $hookName;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function render(string $hookName, array $parameters): string
    {
        $productId = $this->productId($parameters);

        if (0 === $productId) {
            return '';
        }

        $request = $this->requestStack->getCurrentRequest();

        // Lang::getDefaultLanguage() is the shop's language, not the one being browsed.
        $locale = $request?->getLocale() ?? '';

        if ('' === $locale) {
            return '';
        }

        return $this->twig->render('@ProductQuestionModule/theme_hook/product-question.html.twig', [
            'productId' => $productId,
            'locale' => $locale,
            // The "Show more" link of a visitor without JavaScript. The component bounds it.
            'page' => max(1, self::positiveInt($request?->query->all()['questions_page'] ?? null)),
            // The search field posted without JavaScript. Cleaned, and only applied while the
            // product offers a search, by the component.
            'search' => self::text($request?->query->all()['questions_search'] ?? null),
        ]);
    }

    /** A query-string value is a string, an array, or nothing: only digits make a number. */
    private static function positiveInt(mixed $value): int
    {
        return \is_string($value) && ctype_digit($value) ? (int) $value : 0;
    }

    private static function text(mixed $value): string
    {
        return \is_string($value) ? mb_substr($value, 0, 200) : '';
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function productId(array $parameters): int
    {
        $product = $parameters['product'] ?? null;

        if (\is_array($product)) {
            return (int) ($product['id'] ?? 0);
        }

        if (\is_object($product) && method_exists($product, 'getId')) {
            return (int) $product->getId();
        }

        return (int) ($parameters['product_id'] ?? 0);
    }
}
