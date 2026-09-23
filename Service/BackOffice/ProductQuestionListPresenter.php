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
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Repository\ProductTitleSourceInterface;

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
     * }
     */
    public function present(ProductQuestionListFilters $filters, string $uiLocale): array
    {
        $page = $this->storage->searchForModeration($filters);
        $titles = $this->productTitles->titlesFor($this->productIds($page['items']), $uiLocale);

        $rows = [];

        foreach ($page['items'] as $question) {
            $rows[] = $this->row($question, $titles);
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
        ];
    }

    /**
     * @param array<int, string> $titles
     *
     * @return array<string, mixed>
     */
    private function row(ProductQuestion $question, array $titles): array
    {
        $customer = $question->getCustomerId();

        return [
            'id' => $question->getId(),
            'excerpt' => $this->excerpt((string) $question->getContent()),
            'locale' => $question->getLocale(),
            'status' => $this->statusCatalog->get($question->getStatus()),
            'createdAt' => $question->getCreatedAt(),
            'answeredAt' => $question->getAnsweredAt(),
            'productId' => $question->getProductId(),
            'productTitle' => $titles[$question->getProductId()] ?? null,
            'customerId' => $customer,
            // The account may have been deleted since, which the foreign key turns into a
            // null rather than into a missing row: the question stays, the name goes.
            'customerName' => null === $customer ? null : $this->customerName($question),
        ];
    }

    private function customerName(ProductQuestion $question): ?string
    {
        $customer = $question->getCustomer();

        if (null === $customer) {
            return null;
        }

        $name = trim(($customer->getFirstname() ?? '').' '.($customer->getLastname() ?? ''));

        return '' === $name ? $customer->getEmail() : $name;
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
