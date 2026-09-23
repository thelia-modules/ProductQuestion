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
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\BackOffice\ProductQuestionListFilters;
use Symfony\Component\HttpFoundation\Request;

final class ProductQuestionListFiltersTest extends TestCase
{
    private function fromQuery(string $queryString): ProductQuestionListFilters
    {
        return ProductQuestionListFilters::fromRequest(Request::create('/admin/module/product-questions?'.$queryString));
    }

    public function testAnEmptyQueryStringAsksForTheWholeFirstPage(): void
    {
        $filters = $this->fromQuery('');

        self::assertNull($filters->status);
        self::assertNull($filters->productId);
        self::assertNull($filters->customerId);
        self::assertNull($filters->locale);
        self::assertSame(1, $filters->page);
        self::assertSame(ProductQuestionListFilters::DEFAULT_LIMIT, $filters->limit);
        self::assertFalse($filters->hasAnyFilter());
    }

    public function testEveryFilterIsReadFromTheQueryString(): void
    {
        $filters = $this->fromQuery('status=1&product_id=12&customer_id=34&locale=fr_FR&page=3&order=status');

        self::assertSame(ProductQuestionStatus::Answered->value, $filters->status);
        self::assertSame(12, $filters->productId);
        self::assertSame(34, $filters->customerId);
        self::assertSame('fr_FR', $filters->locale);
        self::assertSame(3, $filters->page);
        self::assertSame('status', $filters->order);
        self::assertTrue($filters->hasAnyFilter());
    }

    /**
     * A status the module does not know is no filter at all. Treated as a filter it would
     * match nothing and answer an empty list, which reads like a shop with no questions.
     */
    public function testAStatusNoVersionOfTheModuleWroteIsIgnoredRatherThanMatchingNothing(): void
    {
        self::assertNull($this->fromQuery('status=99')->status);
        self::assertFalse($this->fromQuery('status=99')->hasAnyFilter());
    }

    /**
     * The limit comes from the query string, so it is a number a visitor chose. Left alone it
     * is also how one request asks the database for every row it holds.
     */
    public function testTheNumberOfRowsPerPageIsBounded(): void
    {
        self::assertSame(ProductQuestionListFilters::MAXIMUM_LIMIT, $this->fromQuery('limit=100000')->limit);
        self::assertSame(1, $this->fromQuery('limit=0')->limit);
        self::assertSame(1, $this->fromQuery('limit=-5')->limit);
    }

    public function testAPageBelowTheFirstOneIsTheFirstOne(): void
    {
        self::assertSame(1, $this->fromQuery('page=0')->page);
        self::assertSame(1, $this->fromQuery('page=-3')->page);
    }

    public function testAnUnknownSortFallsBackToTheDefaultOne(): void
    {
        self::assertSame(ProductQuestionListFilters::DEFAULT_ORDER, $this->fromQuery('order=drop_table')->order);
    }

    public function testTheOffsetFollowsThePage(): void
    {
        self::assertSame(0, $this->fromQuery('page=1&limit=20')->offset());
        self::assertSame(40, $this->fromQuery('page=3&limit=20')->offset());
    }

    /**
     * A link built from the filters starts the reading again, otherwise a moderator lands on
     * page four of a selection that now has one page.
     */
    public function testChangingAFilterGoesBackToTheFirstPage(): void
    {
        $filters = $this->fromQuery('status=1&page=4');

        self::assertSame(1, $filters->withStatus(ProductQuestionStatus::Refused->value)->page);
        self::assertSame(1, $filters->withoutFilter('status')->page);
    }

    public function testTheQueryParametersCarryTheFiltersAndNeverThePage(): void
    {
        $params = $this->fromQuery('status=2&locale=fr_FR&page=4')->toQueryParams();

        self::assertSame(['status' => ProductQuestionStatus::Refused->value, 'locale' => 'fr_FR'], $params);
        self::assertArrayNotHasKey('page', $params);
    }

    public function testDroppingOneFilterKeepsTheOthers(): void
    {
        $filters = $this->fromQuery('status=1&locale=fr_FR&product_id=12')->withoutFilter('locale');

        self::assertNull($filters->locale);
        self::assertSame(ProductQuestionStatus::Answered->value, $filters->status);
        self::assertSame(12, $filters->productId);
    }
}
