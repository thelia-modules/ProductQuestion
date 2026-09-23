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

use ProductQuestion\ProductQuestion;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

/**
 * The questions block at the bottom of a product page.
 *
 * The theme declares the point with theme_hook('product.bottom', {product: product}); this
 * is collected through the autoconfigured thelia.theme_hook tag, which is a different
 * mechanism from the back-office hooks and needs no row in the hook table.
 *
 * A product with no answered question in the visitor's language renders nothing at all,
 * rather than an empty heading.
 */
final readonly class ProductQuestionThemeHook implements ThemeHookInterface
{
    private const HOOK_NAME = 'product.bottom';

    public function __construct(
        private Environment $twig,
        private ProductQuestionStorageInterface $storage,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
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

        // Lang::getDefaultLanguage() is the shop's language, not the one being browsed.
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? '';

        if ('' === $locale) {
            return '';
        }

        $questions = $this->storage->findAnsweredForProduct($productId, $locale);

        if ([] === $questions) {
            return '';
        }

        return $this->twig->render('@ProductQuestionModule/theme_hook/product-question.html.twig', [
            'questions' => $questions,
            'labels' => [
                'title' => $this->trans('Customer questions'),
                'answer' => $this->trans('Answer from the shop'),
            ],
        ]);
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

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], ProductQuestion::MESSAGE_DOMAIN);
    }
}
