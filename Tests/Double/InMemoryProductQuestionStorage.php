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

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ProductQuestionStorageInterface;

/**
 * The storage contract, held in an array.
 *
 * It answers the same questions ProductQuestionRepository answers, including the two rules
 * the Propel queries carry and that a test has to be able to break: the front office reads
 * answered questions only, and only in the language it asked for.
 */
final class InMemoryProductQuestionStorage implements ProductQuestionStorageInterface
{
    /** @var list<ProductQuestion> */
    public array $saved = [];

    /** @var list<ProductQuestion> */
    public array $deleted = [];

    private int $nextId = 1;

    /**
     * @param list<ProductQuestion> $questions
     */
    public function __construct(private array $questions = [])
    {
        foreach ($this->questions as $question) {
            if (null === $question->getId()) {
                $question->setId($this->nextId++);
            }
        }
    }

    public function findById(int $id): ?ProductQuestion
    {
        foreach ($this->questions as $question) {
            if ($question->getId() === $id) {
                return $question;
            }
        }

        return null;
    }

    /**
     * @return list<ProductQuestion>
     */
    public function findAnsweredForProduct(int $productId, string $locale): array
    {
        $found = [];

        foreach ($this->questions as $question) {
            if ($question->getProductId() !== $productId) {
                continue;
            }

            if ($question->getLocale() !== $locale) {
                continue;
            }

            if (ProductQuestionStatus::Answered !== $question->getStatusEnum()) {
                continue;
            }

            $found[] = $question;
        }

        return $found;
    }

    /**
     * @return list<ProductQuestion>
     */
    public function findByCustomer(int $customerId): array
    {
        $found = [];

        foreach ($this->questions as $question) {
            if ($question->getCustomerId() === $customerId) {
                $found[] = $question;
            }
        }

        return $found;
    }

    /**
     * @return array<int, int>
     */
    public function countByStatus(): array
    {
        $counts = [];

        foreach ($this->questions as $question) {
            $status = $question->getStatus();

            if (null === $status) {
                continue;
            }

            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    public function save(ProductQuestion $question): void
    {
        if (null === $question->getId()) {
            $question->setId($this->nextId++);
        }

        $this->saved[] = $question;

        if (!\in_array($question, $this->questions, true)) {
            $this->questions[] = $question;
        }
    }

    public function delete(ProductQuestion $question): void
    {
        $this->deleted[] = $question;

        $this->questions = array_values(array_filter(
            $this->questions,
            static fn (ProductQuestion $stored): bool => $stored !== $question,
        ));
    }
}
