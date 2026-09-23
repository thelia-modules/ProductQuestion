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
        return ProductQuestionQuery::create()
            ->filterByProductId($productId)
            ->filterByStatus(ProductQuestionStatus::Answered->value)
            ->filterByLocale($locale)
            // An answer edited later moves up: what the shop last said about the product is
            // what a visitor reads first.
            ->orderByAnsweredAt(Criteria::DESC)
            ->orderById(Criteria::DESC)
            ->find()
            ->getData();
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

    public function save(ProductQuestion $question): void
    {
        $question->save();
    }

    public function delete(ProductQuestion $question): void
    {
        $question->delete();
    }
}
