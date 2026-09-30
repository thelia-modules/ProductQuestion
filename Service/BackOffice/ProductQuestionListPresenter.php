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

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Repository\ProductQuestionAnswerStorageInterface;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Repository\ProductTitleSourceInterface;
use ProductQuestion\Service\CustomerDisplayName;

/**
 * Shapes the moderation list for Twig.
 *
 * The template gets arrays of finished values and makes no decision of its own: no status
 * arithmetic, no translation, no query.
 */
final readonly class ProductQuestionListPresenter
{
    /** How much of a question the list shows before a moderator has to open it. */
    private const EXCERPT_LENGTH = 120;

    public function __construct(
        private ProductQuestionStorageInterface $storage,
        private ProductQuestionStatusCatalog $statusCatalog,
        private ProductTitleSourceInterface $productTitles,
        private ProductQuestionAnswerStorageInterface $answers,
    ) {
    }

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     pageCount: int,
     *     filters: ProductQuestionListFilters,
     *     statuses: array<int, array{value: int, label: string, css: string}>,
     *     counts: array<int, int>,
     *     locales: list<string>,
     *     pendingAnswers: int,
     * }
     */
    public function present(ProductQuestionListFilters $filters, string $uiLocale): array
    {
        $page = $this->storage->searchForModeration($filters);
        $titles = $this->productTitles->titlesFor($this->productIds($page['items']), $uiLocale);
        $pendingAnswers = $this->answers->countPendingByQuestion(array_map(
            static fn (ProductQuestion $question): int => (int) $question->getId(),
            $page['items'],
        ));

        $rows = [];

        foreach ($page['items'] as $question) {
            $rows[] = $this->row($question, $titles, $pendingAnswers[(int) $question->getId()] ?? 0);
        }

        return [
            'rows' => $rows,
            'total' => $page['total'],
            'page' => $filters->page,
            'pageCount' => max(1, (int) ceil($page['total'] / $filters->limit)),
            'filters' => $filters,
            'statuses' => $this->statusCatalog->all(),
            'counts' => $this->storage->countByStatus(),
            'locales' => $this->storage->findUsedLocales(),
            'pendingAnswers' => $this->answers->countPending(),
        ];
    }

    /**
     * @param array<int, string> $titles
     *
     * @return array<string, mixed>
     */
    private function row(ProductQuestion $question, array $titles, int $pendingAnswers): array
    {
        $customer = $question->getCustomerId();

        return [
            'id' => $question->getId(),
            'excerpt' => $this->excerpt((string) $question->getContent()),
            'locale' => $question->getLocale(),
            'status' => $this->statusCatalog->get($question->getStatus()),
            'createdAt' => $question->getCreatedAt(),
            // The customer answers of this question waiting for a moderator.
            'pendingAnswers' => $pendingAnswers,
            'productId' => $question->getProductId(),
            'productTitle' => $titles[$question->getProductId()] ?? null,
            'customerId' => $customer,
            // The account may have been deleted since, which the foreign key turns into a
            // null rather than into a missing row: the question stays, the name goes.
            'customerName' => null === $customer ? null : CustomerDisplayName::of($question),
        ];
    }

    /**
     * @param list<ProductQuestion> $questions
     *
     * @return list<int>
     */
    private function productIds(array $questions): array
    {
        $ids = [];

        foreach ($questions as $question) {
            $id = $question->getProductId();

            if (null !== $id) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function excerpt(string $content): string
    {
        $flat = trim((string) preg_replace('#\s+#u', ' ', $content));

        if (mb_strlen($flat) <= self::EXCERPT_LENGTH) {
            return $flat;
        }

        return mb_substr($flat, 0, self::EXCERPT_LENGTH).'…';
    }
}
