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

namespace ProductQuestion\Repository;

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionQuery;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\BackOffice\ProductQuestionListFilters;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Every Propel query this module makes.
 *
 * Thelia 3 has no loops in Twig, so the back-office controller and the theme hook ask this
 * repository and hand the rows to their template.
 */
final readonly class ProductQuestionRepository implements ProductQuestionStorageInterface
{
    public function findById(int $id): ?ProductQuestion
    {
        return ProductQuestionQuery::create()->findPk($id);
    }

    /**
     * @return list<ProductQuestion>
     */
    public function findAnsweredForProduct(int $productId, string $locale): array
    {
        return $this->answeredForProduct($productId, $locale)
            ->find()
            ->getData();
    }

    /**
     * @return array{items: list<ProductQuestion>, total: int}
     */
    public function findAnsweredForProductPage(int $productId, string $locale, int $offset, int $limit): array
    {
        $query = $this->answeredForProduct($productId, $locale);

        // Counted on a copy, as in searchForModeration().
        $total = (clone $query)->count();

        $items = $query
            ->offset($offset)
            ->limit($limit)
            ->find()
            ->getData();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<ProductQuestion>
     */
    public function findByCustomer(int $customerId): array
    {
        return ProductQuestionQuery::create()
            ->filterByCustomerId($customerId)
            ->orderById(Criteria::ASC)
            ->find()
            ->getData();
    }

    /**
     * @return array<int, int>
     */
    public function countByStatus(): array
    {
        $rows = ProductQuestionQuery::create()
            ->select(['Status'])
            ->withColumn('COUNT(product_question.id)', 'question_count')
            ->groupBy('Status')
            ->find()
            ->getData();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['Status']] = (int) $row['question_count'];
        }

        return $counts;
    }

    /**
     * @return array{items: list<ProductQuestion>, total: int}
     */
    public function searchForModeration(ProductQuestionListFilters $filters): array
    {
        $query = ProductQuestionQuery::create();

        if (null !== $filters->status) {
            $query->filterByStatus($filters->status);
        }

        if (null !== $filters->productId) {
            $query->filterByProductId($filters->productId);
        }

        if (null !== $filters->customerId) {
            $query->filterByCustomerId($filters->customerId);
        }

        if (null !== $filters->locale) {
            $query->filterByLocale($filters->locale);
        }

        // Counted on a copy: count() rewrites the select list of the query it runs on, and
        // this one still has to fetch its rows afterwards.
        $total = (clone $query)->count();

        match ($filters->order) {
            'created' => $query->orderByCreatedAt(Criteria::ASC),
            // Pending first, which is the order of a moderator's work.
            'status' => $query->orderByStatus(Criteria::ASC)->orderByCreatedAt(Criteria::DESC),
            default => $query->orderByCreatedAt(Criteria::DESC),
        };

        $items = $query
            // The list shows who asked. Without this the customer of every row is one query
            // of its own, and the deleted ones would drop the row entirely on an inner join.
            ->joinWith('Customer', Criteria::LEFT_JOIN)
            ->offset($filters->offset())
            ->limit($filters->limit)
            ->find()
            ->getData();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<string>
     */
    public function findUsedLocales(): array
    {
        $rows = ProductQuestionQuery::create()
            ->select(['Locale'])
            ->distinct()
            ->orderByLocale(Criteria::ASC)
            ->find()
            ->getData();

        $locales = [];

        foreach ($rows as $row) {
            // A one-column select answers scalars on some Propel paths and single-key rows on
            // others. Both are read here rather than relying on which one this version takes.
            $locale = \is_array($row) ? ($row['Locale'] ?? null) : $row;

            if (\is_string($locale) && '' !== $locale) {
                $locales[] = $locale;
            }
        }

        return array_values(array_unique($locales));
    }

    private function answeredForProduct(int $productId, string $locale): ProductQuestionQuery
    {
        return ProductQuestionQuery::create()
            ->filterByProductId($productId)
            ->filterByStatus(ProductQuestionStatus::Answered->value)
            ->filterByLocale($locale)
            // An answer edited later moves up: what the shop last said about the product is
            // what a visitor reads first.
            ->orderByAnsweredAt(Criteria::DESC)
            ->orderById(Criteria::DESC);
    }

    public function save(ProductQuestion $question): void
    {
        $question->save();
    }

    public function delete(ProductQuestion $question): void
    {
        $question->delete();
    }
}
