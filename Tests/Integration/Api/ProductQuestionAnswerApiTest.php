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

        self::assertSame([], $this->listedQuestion()['answers']);
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

        $listed = $this->listedQuestion();
        self::assertSame('Yes, flat.', $listed['answer']);
        self::assertSame([(int) $answer->getId()], array_column($listed['answers'], 'id'));
    }

    /**
     * Voting twice counts once, over HTTP as in the block.
     */
    public function testAHelpfulVoteCountsOncePerCustomer(): void
    {
        $answer = (new ProductQuestionAnswer())
            ->setQuestionId((int) $this->question->getId())
            ->setIsOfficial(true)
            ->setContent('Yes, flat.')
            ->setStatusEnum(ProductQuestionStatus::Published)
            ->setPublishedAt(new \DateTimeImmutable());
        $answer->save();
        $token = $this->authenticateAsCustomer();

        $first = $this->jsonRequest('POST', '/api/front/account/product_question_answers/'.$answer->getId().'/helpful', token: $token, format: 'json');
        $second = $this->jsonRequest('POST', '/api/front/account/product_question_answers/'.$answer->getId().'/helpful', token: $token, format: 'json');

        self::assertSame(200, $first->getStatusCode(), (string) $first->getContent());
        self::assertSame(1, self::decodeJson($first)['helpfulCount']);
        self::assertSame(200, $second->getStatusCode());
        self::assertSame(1, self::decodeJson($second)['helpfulCount']);
        self::assertSame(1, ProductQuestionAnswerQuery::create()->findPk($answer->getId())?->getHelpfulCount());
    }

    public function testAVoteOnAnAnswerOffThePageIsANotFound(): void
    {
        $answer = (new ProductQuestionAnswer())
            ->setQuestionId((int) $this->question->getId())
            ->setContent('Pending one.')
            ->setStatusEnum(ProductQuestionStatus::Pending);
        $answer->save();

        $response = $this->jsonRequest('POST', '/api/front/account/product_question_answers/'.$answer->getId().'/helpful', token: $this->authenticateAsCustomer(), format: 'json');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(0, ProductQuestionAnswerQuery::create()->findPk($answer->getId())?->getHelpfulCount());
    }

    public function testAnAnonymousVisitorCannotVote(): void
    {
        self::assertSame(401, $this->jsonRequest('POST', '/api/front/account/product_question_answers/1/helpful', format: 'json')->getStatusCode());
    }

    /**
     * @return array<string, mixed> the one question the product has, as the list serves it
     */
    private function listedQuestion(): array
    {
        /** @var list<array<string, mixed>> $list */
        $list = self::decodeJson($this->jsonRequest('GET', '/api/front/product_questions?productId='.$this->product->getId().'&locale=en_US', format: 'json'));

        self::assertCount(1, $list);

        return $list[0];
    }
}
