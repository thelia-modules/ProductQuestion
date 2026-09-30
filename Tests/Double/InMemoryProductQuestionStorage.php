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
use ProductQuestion\Service\BackOffice\ProductQuestionListFilters;

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

    /** @var list<array{offset: int, limit: int}> */
    public array $answeredPageCalls = [];

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
     * @return array{items: list<ProductQuestion>, total: int}
     */
    public function findAnsweredForProductPage(int $productId, string $locale, int $offset, int $limit): array
    {
        $this->answeredPageCalls[] = ['offset' => $offset, 'limit' => $limit];

        $all = $this->findAnsweredForProduct($productId, $locale);

        return ['items' => \array_slice($all, $offset, $limit), 'total' => \count($all)];
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

    /**
     * @return array{items: list<ProductQuestion>, total: int}
     */
    public function searchForModeration(ProductQuestionListFilters $filters): array
    {
        $matching = [];

        foreach ($this->questions as $question) {
            if (null !== $filters->status && $question->getStatus() !== $filters->status) {
                continue;
            }

            if (null !== $filters->productId && $question->getProductId() !== $filters->productId) {
                continue;
            }

            if (null !== $filters->customerId && $question->getCustomerId() !== $filters->customerId) {
                continue;
            }

            if (null !== $filters->locale && $question->getLocale() !== $filters->locale) {
                continue;
            }

            $matching[] = $question;
        }

        return [
            'items' => \array_slice($matching, $filters->offset(), $filters->limit),
            'total' => \count($matching),
        ];
    }

    /**
     * @return list<string>
     */
    public function findUsedLocales(): array
    {
        $locales = [];

        foreach ($this->questions as $question) {
            $locale = $question->getLocale();

            if (\is_string($locale) && '' !== $locale) {
                $locales[$locale] = $locale;
            }
        }

        sort($locales);

        return $locales;
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
