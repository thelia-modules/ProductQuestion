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

namespace ProductQuestion\Service\BackOffice;

use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\ProductQuestion;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The three statuses with the label and the Bootstrap contextual class the back office shows.
 *
 * The enum holds what is stored; this holds how it is presented, which is a different concern
 * and the only one that needs a translator.
 */
final readonly class ProductQuestionStatusCatalog
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<int, array{value: int, label: string, css: string}>
     */
    public function all(): array
    {
        return [
            ProductQuestionStatus::Pending->value => $this->entry(ProductQuestionStatus::Pending, 'Pending', 'secondary'),
            ProductQuestionStatus::Answered->value => $this->entry(ProductQuestionStatus::Answered, 'Answered', 'success'),
            ProductQuestionStatus::Refused->value => $this->entry(ProductQuestionStatus::Refused, 'Refused', 'danger'),
        ];
    }

    /**
     * The entry for a stored value, including one no version of this module ever wrote: such
     * a row is shown as unknown rather than passed off as a pending question.
     *
     * @return array{value: int, label: string, css: string}
     */
    public function get(?int $status): array
    {
        $known = $this->all();

        if (null !== $status && isset($known[$status])) {
            return $known[$status];
        }

        return [
            'value' => (int) $status,
            'label' => $this->trans('Unknown'),
            'css' => 'light',
        ];
    }

    /**
     * @return array{value: int, label: string, css: string}
     */
    private function entry(ProductQuestionStatus $status, string $label, string $css): array
    {
        return [
            'value' => $status->value,
            'label' => $this->trans($label),
            'css' => $css,
        ];
    }

    private function trans(string $key): string
    {
        // An injected translator defaults to the core domain, where none of these keys exist,
        // and a missing key comes back as the key itself. The domain is never left out.
        return $this->translator->trans($key, [], ProductQuestion::MESSAGE_DOMAIN_BO);
    }
}
