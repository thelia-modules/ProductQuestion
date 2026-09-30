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

use ProductQuestion\Model\ProductQuestionQuery;
use ProductQuestion\Model\ProductQuestionStatus;
use Thelia\Model\LangQuery;
use Thelia\Test\ApiTestCase;

/**
 * The language of a question sent or read through the front API without "locale". An API
 * request has no shop session, so its own locale is the framework's "en", which no product page
 * shows: the question has to land in a language of the shop, and the list has to be read in one.
 */
final class ProductQuestionLanguageApiTest extends ApiTestCase
{
    public function testAQuestionPostedWithoutLocaleIsStoredAndListedInTheShopsDefaultLanguage(): void
    {
        static::getContainer()->get('limiter.product_question_ask_per_ip')->create('127.0.0.1')->reset();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['visible' => 1]);

        $response = $this->jsonRequest('POST', '/api/front/account/product_questions', [
            'productId' => $product->getId(),
            'content' => 'Is it waterproof?',
        ], $this->authenticateAsCustomer());

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        $question = ProductQuestionQuery::create()->filterByProductId($product->getId())->findOne();
        self::assertNotNull($question);

        $defaultLocale = (string) LangQuery::create()->findOneByByDefault(1)?->getLocale();
        self::assertSame($defaultLocale, $question->getLocale());

        $question->setStatusEnum(ProductQuestionStatus::Published)->save();

        $list = $this->jsonRequest('GET', '/api/front/product_questions?productId='.$product->getId(), format: 'json');
        self::assertSame(['Is it waterproof?'], array_column(self::decodeJson($list), 'content'), 'A list read without a language is the one of the default language.');
    }

    public function testAQuestionInALanguageTheShopDoesNotHaveIsRefused(): void
    {
        static::getContainer()->get('limiter.product_question_ask_per_ip')->create('127.0.0.1')->reset();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['visible' => 1]);

        $response = $this->jsonRequest('POST', '/api/front/account/product_questions', [
            'productId' => $product->getId(),
            'locale' => 'xx_XX',
            'content' => 'Is it waterproof?',
        ], $this->authenticateAsCustomer());

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, ProductQuestionQuery::create()->filterByProductId($product->getId())->count());
    }

    /**
     * A language the shop has disabled (lang.active = 0) is refused like one it does not have:
     * the storefront never renders a product page in it.
     */
    public function testAQuestionInALanguageTheShopHasDisabledIsRefused(): void
    {
        static::getContainer()->get('limiter.product_question_ask_per_ip')->create('127.0.0.1')->reset();

        $disabled = LangQuery::create()->filterByActive(false)->findOne();

        if (null === $disabled) {
            $disabled = LangQuery::create()->filterByByDefault(0)->findOne();
            self::assertNotNull($disabled, 'The shop needs a second language for this test.');
            $disabled->setActive(false)->save();
        }

        $locale = (string) $disabled->getLocale();

        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['visible' => 1]);

        $response = $this->jsonRequest('POST', '/api/front/account/product_questions', [
            'productId' => $product->getId(),
            'locale' => $locale,
            'content' => 'Is it waterproof?',
        ], $this->authenticateAsCustomer());

        self::assertSame(422, $response->getStatusCode(), \sprintf('A question in the disabled language "%s" was accepted: %s', $locale, (string) $response->getContent()));
        self::assertSame(0, ProductQuestionQuery::create()->filterByProductId($product->getId())->count());
    }
}
