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

namespace ProductQuestion\Tests\Integration\BackOffice;

use ProductQuestion\Model\ProductQuestionClosedProductQuery;
use ProductQuestion\Service\ModuleConfigProductQuestionSettings;
use ProductQuestion\Tests\Double\AdminSessionInjector;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * Closing questions from the back office: the whole shop from the moderation screen, one
 * product from the Modules tab of its edit page. Both need the session token and are logged.
 */
final class QuestionsClosureScreensTest extends WebIntegrationTestCase
{
    private AdminSessionInjector $injector;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);

        $factory = new FixtureFactory($this->getPropelConnection());
        $this->product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        $admin = $factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    protected function tearDown(): void
    {
        $this->injector->setAdmin(null);
        $this->getService(EventDispatcherInterface::class)->removeSubscriber($this->injector);
        (new ModuleConfigProductQuestionSettings())->setQuestionsClosed(false);

        parent::tearDown();
    }

    public function testTheProductEditPageClosesAndReopensTheProduct(): void
    {
        $crawler = $this->client->request('GET', '/admin/products/update?product_id='.$this->product->getId());

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $card = $crawler->filter('[data-testid="product-question-product-closure"]');
        self::assertCount(1, $card, 'The Modules tab carries the closure switch.');
        self::assertCount(0, $card->filter('input[name="closed"][checked]'));

        $action = (string) $card->attr('action');
        $token = (string) $card->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', $action, ['_token' => 'forged', 'closed' => '1']);
        self::assertFalse($this->isClosed(), 'A forged token changes nothing.');

        $this->client->request('POST', $action, ['_token' => $token, 'closed' => '1']);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('current_tab=modules', (string) $this->client->getResponse()->headers->get('Location'));
        self::assertTrue($this->isClosed());

        $crawler = $this->client->request('GET', '/admin/products/update?product_id='.$this->product->getId());
        self::assertCount(1, $crawler->filter('[data-testid="product-question-product-closure"] input[name="closed"][checked]'));

        // The switch left off: the checkbox is not posted at all.
        $this->client->request('POST', $action, ['_token' => $token]);
        self::assertFalse($this->isClosed());

        self::assertSame(2, AdminLogQuery::create()
            ->filterByResource('ProductQuestion')
            ->filterByMessage('%on product '.$this->product->getId(), Criteria::LIKE)
            ->count());
    }

    public function testTheShopIsClosedFromTheSettingsOfTheModerationScreen(): void
    {
        $crawler = $this->client->request('GET', '/admin/module/ProductQuestion');
        $settings = $crawler->filter('[data-testid="product-question-settings"]');
        self::assertCount(1, $settings->filter('input[name="questions_closed"]'));

        $this->client->request('POST', '/admin/module/ProductQuestion/settings', [
            '_token' => (string) $settings->filter('input[name="_token"]')->attr('value'),
            'questions_closed' => '1',
        ]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertTrue((new ModuleConfigProductQuestionSettings())->questionsClosed());
        self::assertSame(1, AdminLogQuery::create()->filterByResource('ProductQuestion')->filterByMessage('%questions closed%', Criteria::LIKE)->count());

        // The product card says why its own switch does nothing for now.
        $crawler = $this->client->request('GET', '/admin/products/update?product_id='.$this->product->getId());
        self::assertCount(1, $crawler->filter('[data-testid="product-question-shop-closed"]'));
    }

    private function isClosed(): bool
    {
        return ProductQuestionClosedProductQuery::create()->filterByProductId($this->product->getId())->exists();
    }
}
