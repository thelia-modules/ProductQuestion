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
use ProductQuestion\Service\BackOffice\ProductQuestionListFilters;
use ProductQuestion\Service\BackOffice\ProductQuestionListPresenter;
use ProductQuestion\Service\BackOffice\ProductQuestionStatusCatalog;
use ProductQuestion\Tests\Double\FixedTranslator;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use ProductQuestion\Tests\Double\InMemoryProductTitles;

final class ProductQuestionListPresenterTest extends TestCase
{
    private function question(int $productId, string $content, ProductQuestionStatus $status = ProductQuestionStatus::Pending, ?object $customer = null, string $locale = 'fr_FR'): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setProductId($productId)
            ->setCustomerId(null === $customer ? null : 34)
            ->setCustomer($customer)
            ->setLocale($locale)
            ->setContent($content)
            ->setStatusEnum($status);

        return $question;
    }

    private function customer(?string $first, ?string $last, string $email = 'ada@example.com'): object
    {
        return new class($first, $last, $email) {
            public function __construct(private ?string $first, private ?string $last, private string $email)
            {
            }

            public function getFirstname(): ?string
            {
                return $this->first;
            }

            public function getLastname(): ?string
            {
                return $this->last;
            }

            public function getEmail(): string
            {
                return $this->email;
            }
        };
    }

    private function presenter(InMemoryProductQuestionStorage $storage, InMemoryProductTitles $titles): ProductQuestionListPresenter
    {
        return new ProductQuestionListPresenter(
            $storage,
            new ProductQuestionStatusCatalog(new FixedTranslator()),
            $titles,
        );
    }

    public function testEachRowCarriesWhatTheTableShows(): void
    {
        $storage = new InMemoryProductQuestionStorage([
            $this->question(12, 'Est-ce compatible ?', ProductQuestionStatus::Pending, $this->customer('Ada', 'Lovelace')),
        ]);
        $titles = new InMemoryProductTitles(['fr_FR' => [12 => 'Horatio']]);

        $view = $this->presenter($storage, $titles)->present(new ProductQuestionListFilters(), 'fr_FR');

        self::assertCount(1, $view['rows']);
        $row = $view['rows'][0];

        self::assertSame('Est-ce compatible ?', $row['excerpt']);
        self::assertSame('Horatio', $row['productTitle']);
        self::assertSame('Ada Lovelace', $row['customerName']);
        self::assertSame('fr_FR', $row['locale']);
        self::assertStringEndsWith(':Pending', $row['status']['label']);
    }

    /**
     * One query for the whole page. Resolved per row it would be one query per line, which is
     * what a list of twenty questions turns into on a shop with a slow database.
     */
    public function testProductTitlesAreResolvedInASingleCallForThePage(): void
    {
        $storage = new InMemoryProductQuestionStorage([
            $this->question(12, 'Une question ?'),
            $this->question(12, 'Une autre ?'),
            $this->question(99, 'Sur un autre produit ?'),
        ]);
        $titles = new InMemoryProductTitles(['fr_FR' => [12 => 'Horatio', 99 => 'Sigmund']]);

        $this->presenter($storage, $titles)->present(new ProductQuestionListFilters(), 'fr_FR');

        self::assertCount(1, $titles->calls);
        self::assertSame([12, 99], $titles->calls[0]['ids']);
    }

    /**
     * The title follows the language the moderator reads the back office in, not the one the
     * question was asked in.
     */
    public function testTheProductTitleFollowsTheInterfaceLanguage(): void
    {
        $storage = new InMemoryProductQuestionStorage([$this->question(12, 'Is it compatible?', ProductQuestionStatus::Pending, null, 'fr_FR')]);
        $titles = new InMemoryProductTitles(['fr_FR' => [12 => 'Horatio'], 'en_US' => [12 => 'Horatio EN']]);

        $view = $this->presenter($storage, $titles)->present(new ProductQuestionListFilters(), 'en_US');

        self::assertSame('Horatio EN', $view['rows'][0]['productTitle']);
    }

    /**
     * The foreign key is set to null when an account goes, so the question stays and its
     * author does not. The row has to survive that rather than break on it.
     */
    public function testAQuestionOfADeletedAccountStillHasARow(): void
    {
        $storage = new InMemoryProductQuestionStorage([$this->question(12, 'Une question ?')]);

        $view = $this->presenter($storage, new InMemoryProductTitles())->present(new ProductQuestionListFilters(), 'fr_FR');

        self::assertCount(1, $view['rows']);
        self::assertNull($view['rows'][0]['customerName']);
        self::assertNull($view['rows'][0]['customerId']);
    }

    public function testACustomerWithNoNameIsShownByTheirEmail(): void
    {
        $storage = new InMemoryProductQuestionStorage([
            $this->question(12, 'Une question ?', ProductQuestionStatus::Pending, $this->customer(null, null, 'ada@example.com')),
        ]);

        $view = $this->presenter($storage, new InMemoryProductTitles())->present(new ProductQuestionListFilters(), 'fr_FR');

        self::assertSame('ada@example.com', $view['rows'][0]['customerName']);
    }

    /**
     * The table shows an excerpt so a moderator can scan the list. A question of three
     * paragraphs must not push every other row off the screen.
     */
    public function testALongQuestionIsCutDownAndFlattened(): void
    {
        $storage = new InMemoryProductQuestionStorage([
            $this->question(12, "Premiere ligne.\n\nPuis ".str_repeat('du texte ', 40)),
        ]);

        $excerpt = $this->presenter($storage, new InMemoryProductTitles())->present(new ProductQuestionListFilters(), 'fr_FR')['rows'][0]['excerpt'];

        self::assertStringEndsWith('…', $excerpt);
        self::assertStringNotContainsString("\n", $excerpt);
        self::assertLessThanOrEqual(121, mb_strlen($excerpt));
    }

    public function testThePageCountFollowsTheTotalAndTheLimit(): void
    {
        $questions = [];

        for ($i = 0; $i < 25; ++$i) {
            $questions[] = $this->question(12, 'Question numero '.$i);
        }

        $view = $this->presenter(new InMemoryProductQuestionStorage($questions), new InMemoryProductTitles())
            ->present(new ProductQuestionListFilters(limit: 10), 'fr_FR');

        self::assertSame(25, $view['total']);
        self::assertSame(3, $view['pageCount']);
        self::assertCount(10, $view['rows']);
    }

    /**
     * An empty list still has one page: a pagination showing zero pages is a screen with no
     * way back.
     */
    public function testAnEmptyListStillHasOnePage(): void
    {
        $view = $this->presenter(new InMemoryProductQuestionStorage(), new InMemoryProductTitles())
            ->present(new ProductQuestionListFilters(), 'fr_FR');

        self::assertSame(0, $view['total']);
        self::assertSame(1, $view['pageCount']);
        self::assertSame([], $view['rows']);
    }

    /**
     * The filter offers the languages questions were actually asked in. A language nobody
     * wrote in would filter to an empty list every time it is chosen.
     */
    public function testTheLanguageFilterOffersOnlyTheLanguagesInUse(): void
    {
        $storage = new InMemoryProductQuestionStorage([
            $this->question(12, 'Une question ?', ProductQuestionStatus::Pending, null, 'fr_FR'),
            $this->question(12, 'A question?', ProductQuestionStatus::Pending, null, 'en_US'),
            $this->question(12, 'Une autre ?', ProductQuestionStatus::Pending, null, 'fr_FR'),
        ]);

        $view = $this->presenter($storage, new InMemoryProductTitles())->present(new ProductQuestionListFilters(), 'fr_FR');

        self::assertSame(['en_US', 'fr_FR'], $view['locales']);
    }
}
