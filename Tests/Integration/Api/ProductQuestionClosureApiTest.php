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

namespace ProductQuestion\Tests\Integration\Api;

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionQuery;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ClosedProductRepository;
use ProductQuestion\Service\ModuleConfigProductQuestionSettings;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;

/**
 * A closed product over the front API: asking is refused, what was published stays readable.
 */
final class ProductQuestionClosureApiTest extends ApiTestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        static::getContainer()->get('limiter.product_question_ask_per_ip')->create('127.0.0.1')->reset();

        $factory = $this->createFixtureFactory();
        $this->product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['visible' => 1]);
        (new ProductQuestion())
            ->setProductId((int) $this->product->getId())
            ->setLocale('en_US')
            ->setContent('Does it fold?')
            ->setStatusEnum(ProductQuestionStatus::Published)
            ->save();
    }

    protected function tearDown(): void
    {
        (new ModuleConfigProductQuestionSettings())->setQuestionsClosed(false);

        parent::tearDown();
    }

    public function testAQuestionAboutAClosedProductIsForbiddenAndThePublishedOnesStayReadable(): void
    {
        (new ClosedProductRepository())->setClosed((int) $this->product->getId(), true);

        $this->assertAskingIsForbidden();
        $this->assertThePublishedQuestionIsListed();
    }

    public function testNoQuestionIsTakenWhileTheWholeShopIsClosed(): void
    {
        (new ModuleConfigProductQuestionSettings())->setQuestionsClosed(true);

        $this->assertAskingIsForbidden();
        $this->assertThePublishedQuestionIsListed();
    }

    public function testAnOpenProductStillTakesQuestions(): void
    {
        $response = $this->ask();

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
    }

    private function assertAskingIsForbidden(): void
    {
        $response = $this->ask();

        self::assertSame(403, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(1, ProductQuestionQuery::create()->filterByProductId($this->product->getId())->count());
    }

    private function assertThePublishedQuestionIsListed(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/product_questions?productId='.$this->product->getId().'&locale=en_US', format: 'json');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['Does it fold?'], array_column(self::decodeJson($response), 'content'));
    }

    private function ask(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->jsonRequest('POST', '/api/front/account/product_questions', [
            'productId' => $this->product->getId(),
            'locale' => 'en_US',
            'content' => 'Is it waterproof?',
        ], $this->authenticateAsCustomer());
    }
}
