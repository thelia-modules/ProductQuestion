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
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionAnswerQuery;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\ModuleConfigProductQuestionSettings;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;

/**
 * The answers of the front API, over HTTP with a customer's JWT.
 */
final class ProductQuestionAnswerApiTest extends ApiTestCase
{
    private Product $product;

    private ProductQuestion $question;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['visible' => 1]);
        $this->question = (new ProductQuestion())
            ->setProductId((int) $this->product->getId())
            ->setLocale('en_US')
            ->setContent('Does it fold?')
            ->setStatusEnum(ProductQuestionStatus::Published);
        $this->question->save();
    }

    public function testACustomerAnswerIsRefusedWhileTheShopKeepsThemClosed(): void
    {
        (new ModuleConfigProductQuestionSettings())->setAllowsCustomerAnswers(false);
        $token = $this->authenticateAsCustomer();

        $response = $this->jsonRequest('POST', '/api/front/account/product_question_answers', ['questionId' => $this->question->getId(), 'content' => 'Yes it does.'], $token);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, ProductQuestionAnswerQuery::create()->filterByQuestionId($this->question->getId())->count());
    }

    public function testACustomerAnswerIsStoredPendingAndNotReadableBeforeModeration(): void
    {
        (new ModuleConfigProductQuestionSettings())->setAllowsCustomerAnswers(true);
        $token = $this->authenticateAsCustomer();

        $response = $this->jsonRequest('POST', '/api/front/account/product_question_answers', ['questionId' => $this->question->getId(), 'content' => 'Yes, <b>flat</b>.'], $token);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $body = self::decodeJson($response);
        self::assertFalse($body['published']);
        self::assertSame('Yes, flat.', $body['content']);
        self::assertArrayNotHasKey('customerId', $body);

        $stored = ProductQuestionAnswerQuery::create()->findPk($body['id']);
        self::assertSame(ProductQuestionStatus::Pending, $stored?->getStatusEnum());

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/product_question_answers/'.$body['id'])->getStatusCode());

        $list = self::decodeJson($this->jsonRequest('GET', '/api/front/product_questions?productId='.$this->product->getId().'&locale=en_US', format: 'json'));
        self::assertSame([], $list[0]['answers']);
    }

    public function testAnAnonymousVisitorCannotAnswer(): void
    {
        (new ModuleConfigProductQuestionSettings())->setAllowsCustomerAnswers(true);

        $response = $this->jsonRequest('POST', '/api/front/account/product_question_answers', ['questionId' => $this->question->getId(), 'content' => 'Yes it does.']);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testAPublishedAnswerIsReadableWithItsQuestion(): void
    {
        $answer = (new ProductQuestionAnswer())
            ->setQuestionId((int) $this->question->getId())
            ->setIsOfficial(true)
            ->setContent('Yes, flat.')
            ->setStatusEnum(ProductQuestionStatus::Published)
            ->setPublishedAt(new \DateTimeImmutable());
        $answer->save();

        $response = $this->jsonRequest('GET', '/api/front/product_question_answers/'.$answer->getId(), format: 'json');

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue(self::decodeJson($response)['official']);

        $list = self::decodeJson($this->jsonRequest('GET', '/api/front/product_questions?productId='.$this->product->getId().'&locale=en_US', format: 'json'));
        self::assertSame('Yes, flat.', $list[0]['answer']);
        self::assertSame([(int) $answer->getId()], array_column($list[0]['answers'], 'id'));
    }
}
