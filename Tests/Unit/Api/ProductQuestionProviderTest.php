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

namespace ProductQuestion\Tests\Unit\Api;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProductQuestion\Api\Resource\ProductQuestion as ProductQuestionResource;
use ProductQuestion\Api\State\ProductQuestionProvider;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\Api\ProductQuestionLocaleResolver;
use ProductQuestion\Service\Api\ProductQuestionPayloadMapper;
use ProductQuestion\Service\Front\ProductQuestionSearchOffer;
use ProductQuestion\Tests\Double\FixedSettings;
use ProductQuestion\Tests\Double\FixedShopContext;
use ProductQuestion\Tests\Double\InMemoryProductQuestionAnswerStorage;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use ProductQuestion\Tests\Double\InMemoryProductVisibility;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * What the front API lets a client read: the published questions of one product, in one
 * language, and nothing a moderator has not published.
 */
final class ProductQuestionProviderTest extends TestCase
{
    private InMemoryProductQuestionStorage $storage;

    private InMemoryProductQuestionAnswerStorage $answers;

    private FixedSettings $settings;

    protected function setUp(): void
    {
        $this->settings = new FixedSettings();
        $this->answers = new InMemoryProductQuestionAnswerStorage();
        $this->storage = new InMemoryProductQuestionStorage([
            $this->question(1, 12, 'fr_FR', ProductQuestionStatus::Published, 'Compatible ?', 'Oui.'),
            $this->question(2, 12, 'fr_FR', ProductQuestionStatus::Pending, 'En attente ?'),
            $this->question(3, 12, 'fr_FR', ProductQuestionStatus::Refused, 'Refusee ?', 'Brouillon'),
            $this->question(4, 12, 'en_US', ProductQuestionStatus::Published, 'Compatible?', 'Yes.'),
            $this->question(5, 99, 'fr_FR', ProductQuestionStatus::Published, 'Autre ?', 'Oui.'),
        ]);
    }

    private function question(int $id, int $productId, string $locale, ProductQuestionStatus $status, string $content, ?string $answer = null): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setId($id)
            ->setProductId($productId)
            ->setCustomerId(42)
            ->setLocale($locale)
            ->setContent($content)
            ->setStatusEnum($status);

        if (null !== $answer) {
            $this->answers->save((new ProductQuestionAnswer())
                ->setQuestionId($id)
                ->setIsOfficial(true)
                ->setAdminId(7)
                ->setContent($answer)
                ->setStatusEnum(ProductQuestionStatus::Published));
        }

        return $question;
    }

    /**
     * @param list<int> $visibleProductIds
     */
    private function provider(string $requestLocale = 'fr_FR', array $visibleProductIds = [12, 99]): ProductQuestionProvider
    {
        $requestStack = new RequestStack();
        $request = Request::create('/api/front/product_questions');
        $request->setLocale($requestLocale);
        $requestStack->push($request);

        return new ProductQuestionProvider(
            $this->storage,
            new ProductQuestionPayloadMapper(),
            new ProductQuestionLocaleResolver($requestStack, new FixedShopContext('fr_FR', ['fr_FR', 'en_US'])),
            new InMemoryProductVisibility($visibleProductIds),
            $this->answers,
            new ProductQuestionSearchOffer($this->settings),
            $this->settings,
        );
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<ProductQuestionResource>
     */
    private function collection(array $filters, string $requestLocale = 'fr_FR'): array
    {
        $result = $this->provider($requestLocale)->provide(new GetCollection(), [], ['filters' => $filters]);

        self::assertInstanceOf(TraversablePaginator::class, $result);

        /** @var list<ProductQuestionResource> $items */
        $items = array_values(iterator_to_array($result));

        return $items;
    }

    public function testTheListIsThePublishedQuestionsOfOneProductInTheLanguageAsked(): void
    {
        $items = $this->collection(['productId' => 12, 'locale' => 'fr_FR']);

        self::assertSame([1], array_map(static fn (ProductQuestionResource $item): ?int => $item->id, $items));
        self::assertSame('Oui.', $items[0]->answer);
        self::assertTrue($items[0]->published);
    }

    public function testTheLanguageDefaultsToTheRequests(): void
    {
        $items = $this->collection(['productId' => 12], 'en_US');

        self::assertSame([4], array_map(static fn (ProductQuestionResource $item): ?int => $item->id, $items));
    }

    /**
     * An API request has no shop session: its locale is the framework's "en", in which no
     * question is ever asked. The list is the one of the shop's default language instead.
     */
    public function testWithoutALanguageTheListIsTheShopsDefaultOneRatherThanTheFrameworks(): void
    {
        $items = $this->collection(['productId' => 12], 'en');

        self::assertSame([1], array_map(static fn (ProductQuestionResource $item): ?int => $item->id, $items));
    }

    public function testAListWithNoProductIsRefused(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->collection([]);
    }

    /**
     * The filters come from parse_str: `?locale[]=x` is an array. It was a 500 on "Array to
     * string conversion", and `?productId[]=foo` read as product 1.
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function nonScalarFilterProvider(): iterable
    {
        yield 'locale' => [['productId' => 12, 'locale' => ['x']]];
        yield 'productId' => [['productId' => ['foo']]];
        yield 'page' => [['productId' => 12, 'page' => ['2']]];
        yield 'itemsPerPage' => [['productId' => 12, 'itemsPerPage' => ['5']]];
    }

    /**
     * @param array<string, mixed> $filters
     */
    #[DataProvider('nonScalarFilterProvider')]
    public function testAFilterGivenAsAnArrayIsABadRequest(array $filters): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->collection($filters);
    }

    public function testTheListIsPaginated(): void
    {
        for ($i = 10; $i < 15; ++$i) {
            $this->storage->save($this->question($i, 12, 'fr_FR', ProductQuestionStatus::Published, 'Q'.$i, 'R'));
        }

        $result = $this->provider()->provide(new GetCollection(), [], ['filters' => ['productId' => 12, 'itemsPerPage' => 2, 'page' => 2]]);

        self::assertInstanceOf(TraversablePaginator::class, $result);
        self::assertSame(6.0, $result->getTotalItems());
        self::assertSame(3.0, $result->getLastPage());
        self::assertCount(2, iterator_to_array($result));

        // The page is cut by the query, not after reading every answer of the product.
        self::assertSame([['offset' => 2, 'limit' => 2]], $this->storage->publishedPageCalls);
    }

    /**
     * The page is typed by anyone: one past what an integer offset can hold was a TypeError in
     * the storage, a 500. It is an empty page, as any page past the last one.
     */
    public function testAnOversizedPageIsAnEmptyPageRatherThanAnError(): void
    {
        $items = $this->collection(['productId' => 12, 'locale' => 'fr_FR', 'page' => '99999999999999999999']);

        self::assertSame([], $items);
        self::assertGreaterThan(0, $this->storage->publishedPageCalls[0]['offset']);
    }

    public function testOnePublishedQuestionIsReadableByItsId(): void
    {
        $item = $this->provider()->provide(new Get(), ['id' => 1]);

        self::assertInstanceOf(ProductQuestionResource::class, $item);
        self::assertSame('Compatible ?', $item->content);
    }

    /**
     * Knowing an id is not a right to read: a pending or refused question is a 404.
     */
    public function testAnUnpublishedQuestionIsNotFound(): void
    {
        self::assertNull($this->provider()->provide(new Get(), ['id' => 2]));
        self::assertNull($this->provider()->provide(new Get(), ['id' => 3]));
        self::assertNull($this->provider()->provide(new Get(), ['id' => 404]));
    }

    /**
     * The questions follow their product. A product taken offline is a 404 on its page: its
     * answered questions are not listed to a visitor, and the list is the same empty page a
     * product nobody asked about gets, not an error that says the product exists.
     */
    public function testTheQuestionsOfAnOfflineProductAreNotListed(): void
    {
        $result = $this->provider('fr_FR', [99])->provide(new GetCollection(), [], ['filters' => ['productId' => 12, 'locale' => 'fr_FR']]);

        self::assertInstanceOf(TraversablePaginator::class, $result);
        self::assertSame([], iterator_to_array($result));
        self::assertSame(0.0, $result->getTotalItems());
        self::assertSame([], $this->storage->publishedPageCalls);
    }

    public function testAQuestionOfAnOfflineProductIsNotReadableByItsId(): void
    {
        self::assertNull($this->provider('fr_FR', [99])->provide(new Get(), ['id' => 1]));
    }

    /**
     * Above the threshold, `search` narrows the list; the count that decides it is only run
     * when a search is asked for.
     */
    public function testASearchNarrowsTheListAboveTheThreshold(): void
    {
        $this->storage->save($this->question(6, 12, 'fr_FR', ProductQuestionStatus::Published, 'Pliable ?'));
        $this->settings->setSearchThreshold(1);

        self::assertSame([6], array_map(static fn (ProductQuestionResource $item): ?int => $item->id, $this->collection(['productId' => 12, 'locale' => 'fr_FR', 'search' => 'pliable'])));
        self::assertSame(1, $this->storage->publishedCounts);

        $this->collection(['productId' => 12, 'locale' => 'fr_FR']);
        self::assertSame(1, $this->storage->publishedCounts, 'No search, no count.');
    }

    /**
     * Below the threshold the page shows no search field, and the API ignores the parameter.
     */
    public function testASearchBelowTheThresholdIsIgnored(): void
    {
        $this->storage->save($this->question(6, 12, 'fr_FR', ProductQuestionStatus::Published, 'Pliable ?'));

        $this->settings->setSearchThreshold(2);
        self::assertCount(2, $this->collection(['productId' => 12, 'locale' => 'fr_FR', 'search' => 'pliable']));

        $this->settings->setSearchThreshold(0);
        self::assertCount(2, $this->collection(['productId' => 12, 'locale' => 'fr_FR', 'search' => 'pliable']));
        self::assertSame(1, $this->storage->publishedCounts, 'No threshold, no count.');
    }

    /**
     * A shop that shows every language lists them all when the client names none; one named is
     * still honoured.
     */
    public function testEveryLanguageIsListedWhenTheShopShowsThemAll(): void
    {
        $this->settings->setShowsAllLanguages(true);

        $ids = array_map(static fn (ProductQuestionResource $item): ?int => $item->id, $this->collection(['productId' => 12]));
        sort($ids);
        self::assertSame([1, 4], $ids);
        self::assertSame([4], array_map(static fn (ProductQuestionResource $item): ?int => $item->id, $this->collection(['productId' => 12, 'locale' => 'en_US'])));
    }
}
