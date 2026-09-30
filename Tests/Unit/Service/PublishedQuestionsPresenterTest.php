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
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\Front\PublishedQuestionsPresenter;
use ProductQuestion\Tests\Double\InMemoryProductQuestionAnswerStorage;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;

final class PublishedQuestionsPresenterTest extends TestCase
{
    private function question(int $id, int $productId, string $locale, ProductQuestionStatus $status, string $content, int $helpful = 0): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setId($id)
            ->setProductId($productId)
            ->setCustomerId(42)
            ->setLocale($locale)
            ->setContent($content)
            ->setHelpfulCount($helpful)
            ->setStatusEnum($status);

        return $question;
    }

    private function answer(int $id, int $questionId, ProductQuestionStatus $status, string $content, bool $official = false, int $helpful = 0): ProductQuestionAnswer
    {
        $answer = new ProductQuestionAnswer();
        $answer
            ->setId($id)
            ->setQuestionId($questionId)
            ->setIsOfficial($official)
            ->setAdminId($official ? 7 : null)
            ->setCustomerId($official ? null : 55)
            ->setContent($content)
            ->setHelpfulCount($helpful)
            ->setPublishedAt(ProductQuestionStatus::Published === $status ? new \DateTimeImmutable('2026-01-15 10:00:00') : null)
            ->setStatusEnum($status);

        return $answer;
    }

    public function testOnlyPublishedQuestionsOfTheProductInTheLanguageAreShown(): void
    {
        $presenter = new PublishedQuestionsPresenter(new InMemoryProductQuestionStorage([
            $this->question(1, 12, 'fr_FR', ProductQuestionStatus::Published, 'Est-ce compatible ?'),
            $this->question(2, 12, 'fr_FR', ProductQuestionStatus::Pending, 'En attente ?'),
            $this->question(3, 12, 'fr_FR', ProductQuestionStatus::Refused, 'Refusee ?'),
            $this->question(4, 12, 'en_US', ProductQuestionStatus::Published, 'Is it compatible?'),
            $this->question(5, 99, 'fr_FR', ProductQuestionStatus::Published, 'Autre produit ?'),
        ]), new InMemoryProductQuestionAnswerStorage([
            $this->answer(1, 1, ProductQuestionStatus::Published, 'Oui, compatible.', true),
        ]));

        $rows = $presenter->forProduct(12, 'fr_FR')['questions'];

        self::assertCount(1, $rows);
        self::assertSame('Est-ce compatible ?', $rows[0]['content']);
        self::assertSame('Oui, compatible.', $rows[0]['answers'][0]['content']);
        self::assertSame('2026-01-15', $rows[0]['answers'][0]['publishedAt']?->format('Y-m-d'));
    }

    /**
     * A published question with no answer yet is on the page: customers may answer it.
     */
    public function testAPublishedQuestionWithoutAnswerIsShownWithNone(): void
    {
        $presenter = new PublishedQuestionsPresenter(new InMemoryProductQuestionStorage([
            $this->question(1, 12, 'fr_FR', ProductQuestionStatus::Published, 'Q ?'),
        ]), new InMemoryProductQuestionAnswerStorage());

        self::assertSame([], $presenter->forProduct(12, 'fr_FR')['questions'][0]['answers']);
    }

    /**
     * The shop's answer first, then the customers' by helpful votes; pending and refused answers
     * are not on the page.
     */
    public function testTheShopAnswerComesFirstThenTheMostHelpfulAndOnlyPublishedOnes(): void
    {
        $presenter = new PublishedQuestionsPresenter(new InMemoryProductQuestionStorage([
            $this->question(1, 12, 'fr_FR', ProductQuestionStatus::Published, 'Q ?'),
        ]), new InMemoryProductQuestionAnswerStorage([
            $this->answer(1, 1, ProductQuestionStatus::Published, 'Client peu utile', false, 1),
            $this->answer(2, 1, ProductQuestionStatus::Pending, 'En attente', false, 0),
            $this->answer(3, 1, ProductQuestionStatus::Published, 'Client utile', false, 5),
            $this->answer(4, 1, ProductQuestionStatus::Published, 'Boutique', true, 0),
            $this->answer(5, 1, ProductQuestionStatus::Refused, 'Refusee', false, 9),
        ]));

        $answers = $presenter->forProduct(12, 'fr_FR')['questions'][0]['answers'];

        self::assertSame(['Boutique', 'Client utile', 'Client peu utile'], array_column($answers, 'content'));
        self::assertSame([true, false, false], array_column($answers, 'official'));
        self::assertSame([0, 5, 1], array_column($answers, 'helpfulCount'));
    }

    /**
     * The most helpful questions first.
     */
    public function testTheMostHelpfulQuestionsComeFirst(): void
    {
        $presenter = new PublishedQuestionsPresenter(new InMemoryProductQuestionStorage([
            $this->question(1, 12, 'fr_FR', ProductQuestionStatus::Published, 'Peu utile', 1),
            $this->question(2, 12, 'fr_FR', ProductQuestionStatus::Published, 'Utile', 8),
        ]), new InMemoryProductQuestionAnswerStorage());

        self::assertSame(['Utile', 'Peu utile'], array_column($presenter->forProduct(12, 'fr_FR')['questions'], 'content'));
    }

    /**
     * The rows know the customers and the administrator; the page shows none of them.
     */
    public function testNeitherTheAskerNorTheAnswererLeaks(): void
    {
        $presenter = new PublishedQuestionsPresenter(new InMemoryProductQuestionStorage([
            $this->question(1, 12, 'fr_FR', ProductQuestionStatus::Published, 'Q ?'),
        ]), new InMemoryProductQuestionAnswerStorage([
            $this->answer(1, 1, ProductQuestionStatus::Published, 'R.', true),
        ]));

        $rows = $presenter->forProduct(12, 'fr_FR')['questions'];

        self::assertSame(['id', 'content', 'locale', 'answers'], array_keys($rows[0]));
        self::assertSame(['id', 'content', 'official', 'helpfulCount', 'publishedAt'], array_keys($rows[0]['answers'][0]));
    }

    public function testNoProductOrNoLanguageMeansNothing(): void
    {
        $presenter = new PublishedQuestionsPresenter(new InMemoryProductQuestionStorage([
            $this->question(1, 12, 'fr_FR', ProductQuestionStatus::Published, 'Q ?'),
        ]), new InMemoryProductQuestionAnswerStorage());

        self::assertSame(['questions' => [], 'total' => 0, 'published' => 0], $presenter->forProduct(0, 'fr_FR'));
        self::assertSame(['questions' => [], 'total' => 0, 'published' => 0], $presenter->forProduct(12, ''));
    }

    /**
     * A page of the list: the most helpful first, cut at the limit, with the count of them all.
     */
    public function testALimitCutsTheListAndTellsHowManyThereAre(): void
    {
        $storage = new InMemoryProductQuestionStorage([
            $this->question(1, 12, 'fr_FR', ProductQuestionStatus::Published, 'Un', 1),
            $this->question(2, 12, 'fr_FR', ProductQuestionStatus::Published, 'Deux', 3),
            $this->question(3, 12, 'fr_FR', ProductQuestionStatus::Published, 'Trois', 2),
            $this->question(4, 12, 'fr_FR', ProductQuestionStatus::Pending, 'En attente', 9),
        ]);
        $presenter = new PublishedQuestionsPresenter($storage, new InMemoryProductQuestionAnswerStorage());

        $page = $presenter->forProduct(12, 'fr_FR', 2);

        self::assertSame(['Deux', 'Trois'], array_column($page['questions'], 'content'));
        self::assertSame(3, $page['total']);
        self::assertSame([['offset' => 0, 'limit' => 2]], $storage->publishedPageCalls, 'One query for the page, from the first question.');

        self::assertSame(3, $presenter->forProduct(12, 'fr_FR')['total'], 'No limit: every question, counted.');
        self::assertCount(3, $presenter->forProduct(12, 'fr_FR')['questions']);
    }

    /**
     * A search narrows the list and its total; the count of the whole page stays, and is the one
     * query it adds.
     */
    public function testASearchNarrowsTheListAndKeepsTheCountOfThePage(): void
    {
        $storage = new InMemoryProductQuestionStorage([
            $this->question(1, 12, 'fr_FR', ProductQuestionStatus::Published, 'Est-il étanche ?'),
            $this->question(2, 12, 'fr_FR', ProductQuestionStatus::Published, 'Quelle taille ?'),
            $this->question(3, 12, 'fr_FR', ProductQuestionStatus::Published, 'Étanche sous la pluie ?'),
        ]);
        $presenter = new PublishedQuestionsPresenter($storage, new InMemoryProductQuestionAnswerStorage());

        $unfiltered = $presenter->forProduct(12, 'fr_FR');
        self::assertSame([3, 3], [$unfiltered['total'], $unfiltered['published']]);
        self::assertSame(0, $storage->publishedCounts, 'Without a search, the list is its own count.');

        $found = $presenter->forProduct(12, 'fr_FR', 0, 'étanche');
        self::assertSame(['Est-il étanche ?', 'Étanche sous la pluie ?'], array_column($found['questions'], 'content'));
        self::assertSame([2, 3], [$found['total'], $found['published']]);
        self::assertSame(1, $storage->publishedCounts);
    }

    /**
     * Every language when the shop shows them all, each question with its own; the language of
     * the page otherwise.
     */
    public function testEveryLanguageIsShownWhenAskedWithTheLanguageOfEachQuestion(): void
    {
        $presenter = new PublishedQuestionsPresenter(new InMemoryProductQuestionStorage([
            $this->question(1, 12, 'fr_FR', ProductQuestionStatus::Published, 'Est-ce pliable ?'),
            $this->question(2, 12, 'en_US', ProductQuestionStatus::Published, 'Does it fold?'),
            $this->question(3, 12, 'en_US', ProductQuestionStatus::Pending, 'Pending?'),
        ]), new InMemoryProductQuestionAnswerStorage());

        self::assertSame(['en_US'], array_column($presenter->forProduct(12, 'en_US')['questions'], 'locale'));

        $all = $presenter->forProduct(12, 'en_US', 0, null, true);
        self::assertSame(['fr_FR', 'en_US'], array_column($all['questions'], 'locale'));
        self::assertSame(2, $all['total']);
    }
}
