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
 * the published answers of all of them at once. A page of questions adds the count of them all,
 * which the same index answers.
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
     * The first $limit published questions of the product, or all of them when $limit is 0, and
     * how many there are in total: what the block needs to offer the next ones.
     *
     * With a $search, the questions it matches and how many; `published` is always the count of
     * the product page without it, which is what decides whether the search is offered at all.
     * The search costs that one count more, the list without one costs nothing more.
     *
     * @return array{questions: list<array{id: int, content: string, answers: list<array{id: int, content: string, official: bool, helpfulCount: int, publishedAt: ?\DateTimeInterface}>}>, total: int, published: int}
     */
    public function forProduct(int $productId, string $locale, int $limit = 0, ?string $search = null): array
    {
        if ($productId <= 0 || '' === $locale) {
            return ['questions' => [], 'total' => 0, 'published' => 0];
        }

        if ($limit > 0) {
            $page = $this->storage->findPublishedForProductPage($productId, $locale, 0, $limit, $search);
            $questions = $page['items'];
            $total = $page['total'];
        } else {
            $questions = $this->storage->findPublishedForProduct($productId, $locale, $search);
            $total = \count($questions);
        }

        $published = null === $search ? $total : $this->storage->countPublishedForProduct($productId, $locale);

        if ([] === $questions) {
            return ['questions' => [], 'total' => $total, 'published' => $published];
        }

        $answers = $this->answers->findPublishedForQuestions(array_map(
            static fn (ProductQuestion $question): int => (int) $question->getId(),
            $questions,
        ));

        return [
            'questions' => array_map(
                static fn (ProductQuestion $question): array => [
                    'id' => (int) $question->getId(),
                    'content' => (string) $question->getContent(),
                    'answers' => array_map(
                        self::answer(...),
                        $answers[(int) $question->getId()] ?? [],
                    ),
                ],
                $questions,
            ),
            'total' => $total,
            'published' => $published,
        ];
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
