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
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Repository\ProductQuestionAnswerStorageInterface;
use ProductQuestion\Repository\ProductQuestionStorageInterface;

/**
 * The questions a visitor may read on a product page, with their answers, as the block draws
 * them.
 *
 * Two queries whatever the number of questions: the published questions of the product, then
 * the published answers of all of them at once.
 *
 * Rows are turned into arrays here rather than handed to the template: the rows carry who asked
 * and who answered, and neither ever reaches the page. A column added to a table later cannot
 * turn up in the markup by itself.
 */
final readonly class PublishedQuestionsPresenter
{
    public function __construct(
        private ProductQuestionStorageInterface $storage,
        private ProductQuestionAnswerStorageInterface $answers,
    ) {
    }

    /**
     * @return list<array{id: int, content: string, answers: list<array{id: int, content: string, official: bool, helpfulCount: int, publishedAt: ?\DateTimeInterface}>}>
     */
    public function forProduct(int $productId, string $locale): array
    {
        if ($productId <= 0 || '' === $locale) {
            return [];
        }

        $questions = $this->storage->findPublishedForProduct($productId, $locale);

        if ([] === $questions) {
            return [];
        }

        $answers = $this->answers->findPublishedForQuestions(array_map(
            static fn (ProductQuestion $question): int => (int) $question->getId(),
            $questions,
        ));

        return array_map(
            static fn (ProductQuestion $question): array => [
                'id' => (int) $question->getId(),
                'content' => (string) $question->getContent(),
                'answers' => array_map(
                    self::answer(...),
                    $answers[(int) $question->getId()] ?? [],
                ),
            ],
            $questions,
        );
    }

    /**
     * @return array{id: int, content: string, official: bool, helpfulCount: int, publishedAt: ?\DateTimeInterface}
     */
    private static function answer(ProductQuestionAnswer $answer): array
    {
        $publishedAt = $answer->getPublishedAt();

        return [
            'id' => (int) $answer->getId(),
            'content' => (string) $answer->getContent(),
            'official' => $answer->isOfficialAnswer(),
            'helpfulCount' => (int) $answer->getHelpfulCount(),
            // Propel answers a DateTime for a filled column and null for an empty one; the
            // generated signature also allows a string, which only happens when a format is asked.
            'publishedAt' => $publishedAt instanceof \DateTimeInterface ? $publishedAt : null,
        ];
    }
}
