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

use ProductQuestion\Model\ProductQuestionAnswer;

/**
 * Every read and write made on answers and their helpful votes, behind a contract for the same
 * reason as ProductQuestionStorageInterface: the rules live in services a unit test can run.
 */
interface ProductQuestionAnswerStorageInterface
{
    public function findById(int $id): ?ProductQuestionAnswer;

    /** The shop's answer to a question, whatever its status. */
    public function findOfficialForQuestion(int $questionId): ?ProductQuestionAnswer;

    /**
     * Every answer of one question, whatever its status: what the moderation screen lists.
     * The shop's first, then oldest first.
     *
     * @return list<ProductQuestionAnswer>
     */
    public function findForQuestion(int $questionId): array;

    /**
     * The published answers of a set of questions, in one query, keyed by question id: the shop's
     * answer first, then the most helpful, then the oldest.
     *
     * @param list<int> $questionIds
     *
     * @return array<int, list<ProductQuestionAnswer>>
     */
    public function findPublishedForQuestions(array $questionIds): array;

    /**
     * Every answer one customer wrote, oldest first.
     *
     * @return list<ProductQuestionAnswer>
     */
    public function findByCustomer(int $customerId): array;

    /**
     * How many answers wait for a moderator, per question, for a set of questions. A question
     * with none is absent.
     *
     * @param list<int> $questionIds
     *
     * @return array<int, int>
     */
    public function countPendingByQuestion(array $questionIds): array;

    /** How many answers wait for a moderator, across the shop. An indexed count. */
    public function countPending(): int;

    /**
     * Records that a customer found an answer helpful, and recounts the answer and its question.
     *
     * Returns false when that customer's vote was already counted: the database holds the rule,
     * so two clicks racing each other still count once.
     */
    public function addVote(int $answerId, int $customerId): bool;

    /**
     * The ids of the answers one customer voted for, oldest vote first.
     *
     * @return list<int>
     */
    public function findVotedAnswerIdsByCustomer(int $customerId): array;

    /**
     * Cuts the link between a customer and their answers and votes. The answers stay on the page
     * and the votes keep counting; nobody can tell any more whose they were.
     */
    public function detachCustomer(int $customerId): void;

    /**
     * Recounts the helpful votes of the question's published answers onto the question, which is
     * what the product page orders its questions by. Called after anything that publishes,
     * refuses or deletes an answer.
     */
    public function refreshQuestionHelpfulCount(int $questionId): void;

    public function save(ProductQuestionAnswer $answer): void;

    public function delete(ProductQuestionAnswer $answer): void;
}
