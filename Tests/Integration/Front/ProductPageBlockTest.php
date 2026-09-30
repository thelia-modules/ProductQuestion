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

namespace ProductQuestion\Tests\Integration\Front;

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ClosedProductRepository;
use ProductQuestion\Service\ModuleConfigProductQuestionSettings;
use Symfony\Component\DomCrawler\Crawler;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The questions block of a real product page, rendered by the theme for an anonymous visitor.
 */
final class ProductPageBlockTest extends WebIntegrationTestCase
{
    private const PRODUCT_URL = 'product-question-block-test.html';

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $productPage = $this->getService(TemplateHelperInterface::class)->getActiveFrontTemplate()->getAbsolutePath().\DIRECTORY_SEPARATOR.'product.html.twig';

        if (!file_exists($productPage) || !str_contains((string) file_get_contents($productPage), "theme_hook('product.bottom'")) {
            self::markTestSkipped('The installed front-office theme has no product.bottom hook.');
        }

        // Not createFixtureFactory(): its bare request would become the main request of the page.
        $factory = new FixtureFactory($this->getPropelConnection());
        $this->product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['visible' => 1]);
        $this->product->setLocale('en_US')->setTitle('Product under questions')->save($this->getPropelConnection());
        $this->product->setRewrittenUrl('en_US', self::PRODUCT_URL);
    }

    protected function tearDown(): void
    {
        $settings = new ModuleConfigProductQuestionSettings();
        $settings->setQuestionsClosed(false);
        $settings->setQuestionsPerPage(0);

        parent::tearDown();
    }

    public function testAnOpenProductInvitesTheVisitorToSignInAndAsk(): void
    {
        $this->publish('Does it fold?');

        $block = $this->block();

        self::assertCount(1, $block->filter('[data-testid="product-question-sign-in"]'));
        self::assertCount(0, $block->filter('[data-testid="product-question-closed"]'));
    }

    /**
     * Closing stops what customers write, not what they read.
     */
    public function testAClosedProductKeepsItsPublishedQuestionsAndLosesTheForm(): void
    {
        $this->publish('Does it fold?');
        (new ClosedProductRepository())->setClosed((int) $this->product->getId(), true);

        $block = $this->block();

        self::assertStringContainsString('Does it fold?', $block->text());
        self::assertCount(0, $block->filter('[data-testid="product-question-sign-in"]'));
        self::assertCount(0, $block->filter('[data-testid="product-question-form"]'));
        self::assertCount(1, $block->filter('[data-testid="product-question-closed"]'));
    }

    /**
     * The whole shop closed and nothing published: nothing of the module shows on the page.
     */
    public function testAClosedShopWithNothingPublishedShowsNothing(): void
    {
        (new ModuleConfigProductQuestionSettings())->setQuestionsClosed(true);

        $page = $this->page();

        self::assertCount(0, $page->filter('[data-testid="product-question-block"]'));
        self::assertCount(1, $page->filter('[data-testid="product-question-block-closed"][hidden]'));
        self::assertSame('', trim($page->filter('[data-testid="product-question-block-closed"]')->text()));
    }

    /**
     * Out of the box every question is on the page, as in 1.3.0.
     */
    public function testEveryQuestionIsShownUntilTheShopSetsAPageSize(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->publish('Question number '.$i.'?');
        }

        $block = $this->block();

        self::assertCount(5, $block->filter('[data-testid^="product-question-item-"]'));
        self::assertCount(0, $block->filter('[data-testid="product-question-more"]'));
    }

    /**
     * A page at a time, the next one behind a link that works without JavaScript.
     */
    public function testAPageSizeCutsTheListAndTheLinkShowsTheNextPage(): void
    {
        (new ModuleConfigProductQuestionSettings())->setQuestionsPerPage(2);

        for ($i = 1; $i <= 5; ++$i) {
            $this->publish('Question number '.$i.'?');
        }

        $block = $this->block();
        self::assertCount(2, $block->filter('[data-testid^="product-question-item-"]'));
        self::assertSame('?questions_page=2#product-questions', $block->filter('[data-testid="product-question-more"]')->attr('href'));

        $block = $this->block('?questions_page=2');
        self::assertCount(4, $block->filter('[data-testid^="product-question-item-"]'));
        self::assertSame('?questions_page=3#product-questions', $block->filter('[data-testid="product-question-more"]')->attr('href'));

        $block = $this->block('?questions_page=3');
        self::assertCount(5, $block->filter('[data-testid^="product-question-item-"]'));
        self::assertCount(0, $block->filter('[data-testid="product-question-more"]'), 'Nothing left to show.');
    }

    private function publish(string $content, string $locale = 'en_US'): ProductQuestion
    {
        $question = (new ProductQuestion())
            ->setProductId((int) $this->product->getId())
            ->setLocale($locale)
            ->setContent($content)
            ->setStatusEnum(ProductQuestionStatus::Published);
        $question->save();

        return $question;
    }

    private function page(string $query = ''): Crawler
    {
        $this->assertPageRenders('/'.self::PRODUCT_URL.$query);

        return new Crawler((string) $this->client->getResponse()->getContent());
    }

    private function block(string $query = ''): Crawler
    {
        $block = $this->page($query)->filter('[data-testid="product-question-block"]');

        self::assertCount(1, $block, 'The product page carries the questions block.');

        return $block;
    }
}
