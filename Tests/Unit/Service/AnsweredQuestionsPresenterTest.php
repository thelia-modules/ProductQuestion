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

namespace ProductQuestion\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\Front\AnsweredQuestionsPresenter;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;

/**
 * What the product page block is handed: the answered questions of one product in one
 * language, and nothing about who asked or who answered.
 */
final class AnsweredQuestionsPresenterTest extends TestCase
{
    private function question(int $productId, string $locale, ProductQuestionStatus $status, string $content, ?string $answer = null): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setProductId($productId)
            ->setCustomerId(42)
            ->setLocale($locale)
            ->setContent($content)
            ->setAnswer($answer)
            ->setAnsweredBy(7)
            ->setAnsweredAt(null === $answer ? null : new \DateTimeImmutable('2026-01-15 10:00:00'))
            ->setStatusEnum($status);

        return $question;
    }

    public function testOnlyAnsweredQuestionsOfTheProductInTheLanguageAreShown(): void
    {
        $presenter = new AnsweredQuestionsPresenter(new InMemoryProductQuestionStorage([
            $this->question(12, 'fr_FR', ProductQuestionStatus::Answered, 'Est-ce compatible ?', 'Oui, compatible.'),
            $this->question(12, 'fr_FR', ProductQuestionStatus::Pending, 'En attente ?'),
            $this->question(12, 'fr_FR', ProductQuestionStatus::Refused, 'Refusee ?', 'Brouillon'),
            $this->question(12, 'en_US', ProductQuestionStatus::Answered, 'Is it compatible?', 'Yes.'),
            $this->question(99, 'fr_FR', ProductQuestionStatus::Answered, 'Autre produit ?', 'Oui.'),
        ]));

        $rows = $presenter->forProduct(12, 'fr_FR');

        self::assertCount(1, $rows);
        self::assertSame('Est-ce compatible ?', $rows[0]['content']);
        self::assertSame('Oui, compatible.', $rows[0]['answer']);
        self::assertSame('2026-01-15', $rows[0]['answeredAt']?->format('Y-m-d'));
    }

    /**
     * The row knows the customer and the administrator; the page shows neither.
     */
    public function testNeitherTheAskerNorTheAnswererLeaks(): void
    {
        $presenter = new AnsweredQuestionsPresenter(new InMemoryProductQuestionStorage([
            $this->question(12, 'fr_FR', ProductQuestionStatus::Answered, 'Q ?', 'R.'),
        ]));

        $rows = $presenter->forProduct(12, 'fr_FR');

        self::assertSame(['id', 'content', 'answer', 'answeredAt'], array_keys($rows[0]));
    }

    public function testNoProductOrNoLanguageMeansNothing(): void
    {
        $storage = new InMemoryProductQuestionStorage([
            $this->question(12, 'fr_FR', ProductQuestionStatus::Answered, 'Q ?', 'R.'),
        ]);
        $presenter = new AnsweredQuestionsPresenter($storage);

        self::assertSame([], $presenter->forProduct(0, 'fr_FR'));
        self::assertSame([], $presenter->forProduct(12, ''));
    }
}
