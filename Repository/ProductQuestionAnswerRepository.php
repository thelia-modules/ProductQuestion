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

use ProductQuestion\Model\Map\ProductQuestionAnswerTableMap;
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionAnswerQuery;
use ProductQuestion\Model\ProductQuestionAnswerVoteQuery;
use ProductQuestion\Model\ProductQuestionStatus;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;

final readonly class ProductQuestionAnswerRepository implements ProductQuestionAnswerStorageInterface
{
    public function findById(int $id): ?ProductQuestionAnswer
    {
        return ProductQuestionAnswerQuery::create()->findPk($id);
    }

    public function findOfficialForQuestion(int $questionId): ?ProductQuestionAnswer
    {
        return ProductQuestionAnswerQuery::create()
            ->filterByQuestionId($questionId)
            ->filterByIsOfficial(true)
            ->orderById(Criteria::ASC)
            ->findOne();
    }

    public function findForQuestion(int $questionId): array
    {
        return ProductQuestionAnswerQuery::create()
            ->filterByQuestionId($questionId)
            ->orderByIsOfficial(Criteria::DESC)
            ->orderById(Criteria::ASC)
            ->find()
            ->getData();
    }

    public function findPublishedForQuestions(array $questionIds): array
    {
        if ([] === $questionIds) {
            return [];
        }

        $answers = ProductQuestionAnswerQuery::create()
            ->filterByQuestionId(array_values(array_unique($questionIds)), Criteria::IN)
            ->filterByStatus(ProductQuestionStatus::Published->value)
            ->orderByIsOfficial(Criteria::DESC)
            ->orderByHelpfulCount(Criteria::DESC)
            ->orderById(Criteria::ASC)
            ->find();

        $byQuestion = [];

        foreach ($answers as $answer) {
            $byQuestion[(int) $answer->getQuestionId()][] = $answer;
        }

        return $byQuestion;
    }

    public function findByCustomer(int $customerId): array
    {
        return ProductQuestionAnswerQuery::create()
            ->filterByCustomerId($customerId)
            ->orderById(Criteria::ASC)
            ->find()
            ->getData();
    }

    public function countPendingByQuestion(array $questionIds): array
    {
        if ([] === $questionIds) {
            return [];
        }

        $rows = ProductQuestionAnswerQuery::create()
            ->filterByQuestionId(array_values(array_unique($questionIds)), Criteria::IN)
            ->filterByStatus(ProductQuestionStatus::Pending->value)
            ->select(['QuestionId'])
            ->withColumn('COUNT(product_question_answer.id)', 'answer_count')
            ->groupBy('QuestionId')
            ->find()
            ->getData();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['QuestionId']] = (int) $row['answer_count'];
        }

        return $counts;
    }

    public function countPending(): int
    {
        return ProductQuestionAnswerQuery::create()
            ->filterByStatus(ProductQuestionStatus::Pending->value)
            ->count();
    }

    public function addVote(int $answerId, int $customerId): bool
    {
        $con = Propel::getWriteConnection(ProductQuestionAnswerTableMap::DATABASE_NAME);

        // INSERT IGNORE rather than a read then a write: the unique key on (answer, customer)
        // is what decides, so a double click cannot slip a second vote between the two.
        $insert = $con->prepare('INSERT IGNORE INTO product_question_answer_vote (answer_id, customer_id, created_at, updated_at) VALUES (:answer, :customer, NOW(), NOW())');
        $insert->execute(['answer' => $answerId, 'customer' => $customerId]);

        if (0 === $insert->rowCount()) {
            return false;
        }

        // Recounted from the votes rather than incremented, so the column cannot drift from them.
        $con->prepare('UPDATE product_question_answer SET helpful_count = (SELECT COUNT(*) FROM product_question_answer_vote v WHERE v.answer_id = product_question_answer.id) WHERE id = :answer')
            ->execute(['answer' => $answerId]);

        $question = $con->prepare('SELECT question_id FROM product_question_answer WHERE id = :answer');
        $question->execute(['answer' => $answerId]);
        $questionId = (int) $question->fetchColumn();

        if ($questionId > 0) {
            $this->refreshQuestionHelpfulCount($questionId);
        }

        return true;
    }

    public function findVotedAnswerIdsByCustomer(int $customerId): array
    {
        $ids = ProductQuestionAnswerVoteQuery::create()
            ->filterByCustomerId($customerId)
            ->orderById(Criteria::ASC)
            ->select(['AnswerId'])
            ->find()
            ->getData();

        return array_map(static fn (mixed $id): int => (int) $id, array_values($ids));
    }

    public function detachCustomer(int $customerId): void
    {
        ProductQuestionAnswerQuery::create()
            ->filterByCustomerId($customerId)
            ->update(['CustomerId' => null]);

        // A NULL takes no part in the unique key: the anonymized votes keep counting.
        ProductQuestionAnswerVoteQuery::create()
            ->filterByCustomerId($customerId)
            ->update(['CustomerId' => null]);
    }

    public function refreshQuestionHelpfulCount(int $questionId): void
    {
        Propel::getWriteConnection(ProductQuestionAnswerTableMap::DATABASE_NAME)
            ->prepare('UPDATE product_question SET helpful_count = (SELECT COALESCE(SUM(a.helpful_count), 0) FROM product_question_answer a WHERE a.question_id = product_question.id AND a.status = :published) WHERE id = :question')
            ->execute(['published' => ProductQuestionStatus::Published->value, 'question' => $questionId]);
    }

    public function save(ProductQuestionAnswer $answer): void
    {
        $answer->save();
    }

    public function delete(ProductQuestionAnswer $answer): void
    {
        $answer->delete();
    }
}
