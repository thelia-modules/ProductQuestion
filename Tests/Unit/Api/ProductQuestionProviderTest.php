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
use PHPUnit\Framework\TestCase;
use ProductQuestion\Api\Resource\ProductQuestion as ProductQuestionResource;
use ProductQuestion\Api\State\ProductQuestionProvider;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\Api\ProductQuestionPayloadMapper;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * What the front API lets a client read: the answered questions of one product, in one
 * language, and nothing a moderator has not published.
 */
final class ProductQuestionProviderTest extends TestCase
{
    private InMemoryProductQuestionStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryProductQuestionStorage([
            $this->question(1, 12, 'fr_FR', ProductQuestionStatus::Answered, 'Compatible ?', 'Oui.'),
            $this->question(2, 12, 'fr_FR', ProductQuestionStatus::Pending, 'En attente ?'),
            $this->question(3, 12, 'fr_FR', ProductQuestionStatus::Refused, 'Refusee ?', 'Brouillon'),
            $this->question(4, 12, 'en_US', ProductQuestionStatus::Answered, 'Compatible?', 'Yes.'),
            $this->question(5, 99, 'fr_FR', ProductQuestionStatus::Answered, 'Autre ?', 'Oui.'),
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
            ->setAnswer($answer)
            ->setStatusEnum($status);

        return $question;
    }

    private function provider(string $requestLocale = 'fr_FR'): ProductQuestionProvider
    {
        $requestStack = new RequestStack();
        $request = Request::create('/api/front/product_questions');
        $request->setLocale($requestLocale);
        $requestStack->push($request);

        return new ProductQuestionProvider($this->storage, new ProductQuestionPayloadMapper(), $requestStack);
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

    public function testTheListIsTheAnsweredQuestionsOfOneProductInTheLanguageAsked(): void
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

    public function testAListWithNoProductIsRefused(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->collection([]);
    }

    public function testTheListIsPaginated(): void
    {
        for ($i = 10; $i < 15; ++$i) {
            $this->storage->save($this->question($i, 12, 'fr_FR', ProductQuestionStatus::Answered, 'Q'.$i, 'R'));
        }

        $result = $this->provider()->provide(new GetCollection(), [], ['filters' => ['productId' => 12, 'itemsPerPage' => 2, 'page' => 2]]);

        self::assertInstanceOf(TraversablePaginator::class, $result);
        self::assertSame(6.0, $result->getTotalItems());
        self::assertSame(3.0, $result->getLastPage());
        self::assertCount(2, iterator_to_array($result));
    }

    public function testOneAnsweredQuestionIsReadableByItsId(): void
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
}
