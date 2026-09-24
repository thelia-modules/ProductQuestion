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

namespace ProductQuestion\Service\Front;

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Repository\ProductQuestionStorageInterface;

/**
 * The questions a visitor may read on a product page, as the block draws them.
 *
 * Rows are turned into arrays here rather than handed to the template: the row carries who
 * asked and who answered, and neither ever reaches the page. A column added to the table
 * later cannot turn up in the markup by itself.
 */
final readonly class AnsweredQuestionsPresenter
{
    public function __construct(
        private ProductQuestionStorageInterface $storage,
    ) {
    }

    /**
     * @return list<array{id: int, content: string, answer: string, answeredAt: ?\DateTimeInterface}>
     */
    public function forProduct(int $productId, string $locale): array
    {
        if ($productId <= 0 || '' === $locale) {
            return [];
        }

        return array_map(
            static fn (ProductQuestion $question): array => [
                'id' => (int) $question->getId(),
                'content' => (string) $question->getContent(),
                'answer' => (string) $question->getAnswer(),
                'answeredAt' => self::date($question->getAnsweredAt()),
            ],
            $this->storage->findAnsweredForProduct($productId, $locale),
        );
    }

    /**
     * Propel answers a DateTime for a filled column and null for an empty one; the generated
     * signature also allows a string, which only happens when a format was asked for.
     */
    private static function date(mixed $value): ?\DateTimeInterface
    {
        return $value instanceof \DateTimeInterface ? $value : null;
    }
}
