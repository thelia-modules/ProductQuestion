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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the mail telling the shop about a new question carries.
 *
 * Built here, as plain values, rather than by handing the row to the template: the mail
 * shows the question, who asked and which product, and nothing else the row knows. The
 * strings are translated here too, in the shop's language, because Twig's |trans does not
 * reach a module's catalogue from an email template.
 */
final readonly class ProductQuestionNotification
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{
     *     question: array{id: int, content: string, locale: string, customerName: string, productTitle: string, adminUrl: string},
     *     labels: array<string, string>
     * }
     */
    public function parameters(
        int $questionId,
        string $content,
        string $questionLocale,
        ?string $customerName,
        ?string $productTitle,
        string $adminUrl,
        string $shopLocale,
    ): array {
        $productTitle ??= $this->trans('Product #%id', ['%id' => $questionId], $shopLocale);
        $customerName = null === $customerName || '' === trim($customerName)
            ? $this->trans('A customer', [], $shopLocale)
            : $customerName;

        return [
            'question' => [
                'id' => $questionId,
                'content' => $content,
                'locale' => $questionLocale,
                'customerName' => $customerName,
                'productTitle' => $productTitle,
                'adminUrl' => $adminUrl,
            ],
            'labels' => [
                'subject' => $this->trans('New customer question about "%product"', ['%product' => $productTitle], $shopLocale),
                'heading' => $this->trans('New customer question', [], $shopLocale),
                'intro' => $this->trans('%customer asked a question about "%product", in %locale:', ['%customer' => $customerName, '%product' => $productTitle, '%locale' => $questionLocale], $shopLocale),
                'link' => $this->trans('Answer it from the back office', [], $shopLocale),
                'linkWithUrl' => $this->trans('Answer it from the back office: %url', ['%url' => $adminUrl], $shopLocale),
                'outro' => $this->trans('The question appears on the product page once you have answered it.', [], $shopLocale),
            ],
        ];
    }

    /**
     * @param array<string, string|int> $parameters
     */
    private function trans(string $id, array $parameters, string $locale): string
    {
        return $this->translator->trans($id, $parameters, ProductQuestionModule::MESSAGE_DOMAIN_EMAIL, $locale);
    }
}
