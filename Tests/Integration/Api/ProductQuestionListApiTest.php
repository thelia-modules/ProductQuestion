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
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\ModuleConfigProductQuestionSettings;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;

/**
 * The public list of a product's questions over HTTP, with the shop's settings applied.
 */
final class ProductQuestionListApiTest extends ApiTestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['visible' => 1]);

        foreach (['Is it waterproof?', 'How big is it?', 'Does it fold?'] as $content) {
            $this->publish($content, 'en_US');
        }
    }

    protected function tearDown(): void
    {
        $settings = new ModuleConfigProductQuestionSettings();
        $settings->setSearchThreshold(0);
        $settings->setShowsAllLanguages(false);

        parent::tearDown();
    }

    public function testASearchNarrowsTheListAboveTheThresholdAndIsIgnoredBelow(): void
    {
        $settings = new ModuleConfigProductQuestionSettings();

        $settings->setSearchThreshold(2);
        self::assertSame(['Is it waterproof?'], $this->listed('&locale=en_US&search=waterproof'));

        $settings->setSearchThreshold(3);
        self::assertCount(3, $this->listed('&locale=en_US&search=waterproof'), 'Three questions, threshold three: no search.');
    }

    public function testEveryLanguageIsListedWhenTheShopShowsThemAll(): void
    {
        $this->publish('Est-ce pliable ?', 'fr_FR');

        self::assertNotContains('Est-ce pliable ?', $this->listed('&locale=en_US'));
        self::assertNotContains('Est-ce pliable ?', $this->listed(''), 'Off: one language, the default one for an API request.');

        (new ModuleConfigProductQuestionSettings())->setShowsAllLanguages(true);

        self::assertContains('Est-ce pliable ?', $this->listed(''));
        self::assertCount(4, $this->listed(''));
        self::assertSame(['Est-ce pliable ?'], $this->listed('&locale=fr_FR'), 'A language named is still honoured.');
    }

    private function publish(string $content, string $locale): void
    {
        (new ProductQuestion())
            ->setProductId((int) $this->product->getId())
            ->setLocale($locale)
            ->setContent($content)
            ->setStatusEnum(ProductQuestionStatus::Published)
            ->save();
    }

    /**
     * @return list<string>
     */
    private function listed(string $query): array
    {
        $response = $this->jsonRequest('GET', '/api/front/product_questions?productId='.$this->product->getId().$query, format: 'json');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return array_column(self::decodeJson($response), 'content');
    }
}
