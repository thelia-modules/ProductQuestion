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

namespace ProductQuestion\Tests\Double;

use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ProductQuestionAnswerStorageInterface;

/**
 * The answer storage contract, held in arrays, with the rules the Propel queries carry: the
 * product page reads published answers only, the shop's first, then the most helpful; a
 * customer votes once per answer.
 */
final class InMemoryProductQuestionAnswerStorage implements ProductQuestionAnswerStorageInterface
{
    /** @var list<ProductQuestionAnswer> */
    public array $saved = [];

    /** @var list<ProductQuestionAnswer> */
    public array $deleted = [];

    /** @var list<int> */
    public array $refreshedQuestions = [];

    /** @var list<array{answer: int, customer: ?int}> */
    public array $votes = [];

    private int $nextId = 1;

    /**
     * @param list<ProductQuestionAnswer> $answers
     */
    public function __construct(private array $answers = [])
    {
        foreach ($this->answers as $answer) {
            if (null === $answer->getId()) {
                $answer->setId($this->nextId++);
            } else {
                $this->nextId = max($this->nextId, (int) $answer->getId() + 1);
            }
        }
    }

    public function findById(int $id): ?ProductQuestionAnswer
    {
        foreach ($this->answers as $answer) {
            if ($answer->getId() === $id) {
                return $answer;
            }
        }

        return null;
    }

    public function findOfficialForQuestion(int $questionId): ?ProductQuestionAnswer
    {
        foreach ($this->answers as $answer) {
            if ($answer->getQuestionId() === $questionId && $answer->isOfficialAnswer()) {
                return $answer;
            }
        }

        return null;
    }

    public function findForQuestion(int $questionId): array
    {
        $found = array_values(array_filter(
            $this->answers,
            static fn (ProductQuestionAnswer $answer): bool => $answer->getQuestionId() === $questionId,
        ));

        usort($found, static fn (ProductQuestionAnswer $a, ProductQuestionAnswer $b): int => [(int) !$a->isOfficialAnswer(), $a->getId()] <=> [(int) !$b->isOfficialAnswer(), $b->getId()]);

        return $found;
    }

    public function findPublishedForQuestions(array $questionIds): array
    {
        $found = array_values(array_filter(
            $this->answers,
            static fn (ProductQuestionAnswer $answer): bool => \in_array($answer->getQuestionId(), $questionIds, true)
                && ProductQuestionStatus::Published === $answer->getStatusEnum(),
        ));

        usort($found, static fn (ProductQuestionAnswer $a, ProductQuestionAnswer $b): int => [(int) !$a->isOfficialAnswer(), -(int) $a->getHelpfulCount(), $a->getId()] <=> [(int) !$b->isOfficialAnswer(), -(int) $b->getHelpfulCount(), $b->getId()]);

        $byQuestion = [];

        foreach ($found as $answer) {
            $byQuestion[(int) $answer->getQuestionId()][] = $answer;
        }

        return $byQuestion;
    }

    public function findByCustomer(int $customerId): array
    {
        return array_values(array_filter(
            $this->answers,
            static fn (ProductQuestionAnswer $answer): bool => $answer->getCustomerId() === $customerId,
        ));
    }

    public function countPendingByQuestion(array $questionIds): array
    {
        $counts = [];

        foreach ($this->answers as $answer) {
            $questionId = (int) $answer->getQuestionId();

            if (\in_array($questionId, $questionIds, true) && ProductQuestionStatus::Pending === $answer->getStatusEnum()) {
                $counts[$questionId] = ($counts[$questionId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function countPending(): int
    {
        return array_sum($this->countPendingByQuestion(array_map(
            static fn (ProductQuestionAnswer $answer): int => (int) $answer->getQuestionId(),
            $this->answers,
        )));
    }

    public function addVote(int $answerId, int $customerId): bool
    {
        foreach ($this->votes as $vote) {
            if ($vote['answer'] === $answerId && $vote['customer'] === $customerId) {
                return false;
            }
        }

        $this->votes[] = ['answer' => $answerId, 'customer' => $customerId];

        $answer = $this->findById($answerId);
        $answer?->setHelpfulCount((int) $answer->getHelpfulCount() + 1);

        return true;
    }

    public function findVotedAnswerIdsByCustomer(int $customerId): array
    {
        $ids = [];

        foreach ($this->votes as $vote) {
            if ($vote['customer'] === $customerId) {
                $ids[] = $vote['answer'];
            }
        }

        return $ids;
    }

    public function detachCustomer(int $customerId): void
    {
        foreach ($this->findByCustomer($customerId) as $answer) {
            $answer->setCustomerId(null);
        }

        foreach ($this->votes as $index => $vote) {
            if ($vote['customer'] === $customerId) {
                $this->votes[$index]['customer'] = null;
            }
        }
    }

    public function refreshQuestionHelpfulCount(int $questionId): void
    {
        $this->refreshedQuestions[] = $questionId;
    }

    public function save(ProductQuestionAnswer $answer): void
    {
        if (null === $answer->getId()) {
            $answer->setId($this->nextId++);
        }

        $this->saved[] = $answer;

        if (!\in_array($answer, $this->answers, true)) {
            $this->answers[] = $answer;
        }
    }

    public function delete(ProductQuestionAnswer $answer): void
    {
        $this->deleted[] = $answer;

        $this->answers = array_values(array_filter(
            $this->answers,
            static fn (ProductQuestionAnswer $stored): bool => $stored !== $answer,
        ));
    }
}
