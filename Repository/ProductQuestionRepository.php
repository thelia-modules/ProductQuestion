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
use ProductQuestion\Service\Front\QuestionSearchTerm;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\CustomerQuery;

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
    public function findPublishedForProduct(int $productId, ?string $locale, ?string $search = null): array
    {
        return $this->publishedForProduct($productId, $locale, $search)
            ->find()
            ->getData();
    }

    /**
     * @return array{items: list<ProductQuestion>, total: int}
     */
    public function findPublishedForProductPage(int $productId, ?string $locale, int $offset, int $limit, ?string $search = null): array
    {
        $query = $this->publishedForProduct($productId, $locale, $search);

        // Counted on a copy, as in searchForModeration().
        $total = (clone $query)->count();

        $items = $query
            ->offset($offset)
            ->limit($limit)
            ->find()
            ->getData();

        return ['items' => $items, 'total' => $total];
    }

    public function countPublishedForProduct(int $productId, ?string $locale): int
    {
        return $this->publishedForProduct($productId, $locale)->count();
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

    public function countPending(): int
    {
        return ProductQuestionQuery::create()
            ->filterByStatus(ProductQuestionStatus::Pending->value)
            ->count();
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

        if ($filters->pendingAnswers) {
            // The questions a customer answered and a moderator has not read yet.
            $query->where(\sprintf(
                'EXISTS (SELECT 1 FROM product_question_answer pqa WHERE pqa.question_id = product_question.id AND pqa.status = %d)',
                ProductQuestionStatus::Pending->value,
            ));
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
            ->offset($filters->offset())
            ->limit($filters->limit)
            ->find()
            ->getData();

        $this->attachCustomers($items);

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

    /**
     * The list shows who asked: the customers of a page are read in one query, rather than one
     * query per row.
     *
     * Not a joinWith('Customer', LEFT JOIN): on a question nobody owns any more, the join
     * hydrates an empty customer and sets the question's customer_id to 0. The question sits in
     * the instance pool, so the next save of it in the same process breaks the foreign key.
     *
     * @param list<ProductQuestion> $questions
     */
    private function attachCustomers(array $questions): void
    {
        $customerIds = array_values(array_unique(array_filter(array_map(
            static fn (ProductQuestion $question): ?int => $question->getCustomerId(),
            $questions,
        ))));

        if ([] === $customerIds) {
            return;
        }

        $customers = [];

        foreach (CustomerQuery::create()->filterById($customerIds, Criteria::IN)->find() as $customer) {
            $customers[(int) $customer->getId()] = $customer;
        }

        foreach ($questions as $question) {
            $customer = $customers[(int) $question->getCustomerId()] ?? null;

            if (null !== $customer) {
                $question->setCustomer($customer);
            }
        }
    }

    private function publishedForProduct(int $productId, ?string $locale, ?string $search = null): ProductQuestionQuery
    {
        $query = ProductQuestionQuery::create()
            ->filterByProductId($productId)
            ->filterByStatus(ProductQuestionStatus::Published->value);

        if (null !== $locale) {
            $query->filterByLocale($locale);
        }

        if (null !== $search) {
            // The question, or one of its published answers: a visitor looking for "waterproof"
            // wants the question the shop answered with the word as well as the one asking it.
            $pattern = QuestionSearchTerm::likePattern($search);
            $query
                ->condition('pq_search_question', 'product_question.content LIKE ?', $pattern, \PDO::PARAM_STR)
                ->condition('pq_search_answer', \sprintf(
                    'EXISTS (SELECT 1 FROM product_question_answer pqa_search WHERE pqa_search.question_id = product_question.id AND pqa_search.status = %d AND pqa_search.content LIKE ?)',
                    ProductQuestionStatus::Published->value,
                ), $pattern, \PDO::PARAM_STR)
                ->where(['pq_search_question', 'pq_search_answer'], Criteria::LOGICAL_OR);
        }

        return $query
            // The most useful first: the helpful votes of the question's published answers,
            // summed onto the row. Among equals, the most recent question.
            ->orderByHelpfulCount(Criteria::DESC)
            ->orderByCreatedAt(Criteria::DESC)
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
