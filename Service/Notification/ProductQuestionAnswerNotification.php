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
 * What the customer is told once an answer is published: their question, the answer, the
 * product page it now sits on, and the link to stop these mails. In the language they asked in — the answer is written in it.
 */
final readonly class ProductQuestionAnswerNotification
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{
     *     question: array{id: int, content: string, answer: string, productTitle: string, productUrl: string, unsubscribeUrl: string},
     *     labels: array<string, string>
     * }
     */
    public function parameters(
        int $questionId,
        string $content,
        string $answer,
        ?string $productTitle,
        string $productUrl,
        string $locale,
        bool $official = true,
        string $unsubscribeUrl = '',
    ): array {
        $productTitle ??= $this->trans('the product', [], $locale);

        return [
            'question' => [
                'id' => $questionId,
                'content' => $content,
                'answer' => $answer,
                'productTitle' => $productTitle,
                'productUrl' => $productUrl,
                'unsubscribeUrl' => $unsubscribeUrl,
            ],
            'labels' => [
                'subject' => $official
                    ? $this->trans('Our answer to your question about "%product"', ['%product' => $productTitle], $locale)
                    : $this->trans('A customer answered your question about "%product"', ['%product' => $productTitle], $locale),
                'heading' => $official
                    ? $this->trans('We have answered your question', [], $locale)
                    : $this->trans('Your question has a new answer', [], $locale),
                'intro' => $this->trans('You asked about "%product":', ['%product' => $productTitle], $locale),
                'answer' => $official ? $this->trans('Our answer:', [], $locale) : $this->trans('A customer answered:', [], $locale),
                'link' => $this->trans('See it on the product page', [], $locale),
                'linkWithUrl' => $this->trans('See it on the product page: %url', ['%url' => $productUrl], $locale),
                'outro' => $this->trans('Thank you for your interest.', [], $locale),
                'unsubscribe' => $this->trans('Stop the emails about this question', [], $locale),
                'unsubscribeWithUrl' => $this->trans('Stop the emails about this question: %url', ['%url' => $unsubscribeUrl], $locale),
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
