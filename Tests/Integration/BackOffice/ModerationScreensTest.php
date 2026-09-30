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

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionAnswerQuery;
use ProductQuestion\Model\ProductQuestionQuery;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\ModuleConfigProductQuestionSettings;
use ProductQuestion\Tests\Double\AdminSessionInjector;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\AdminLogQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * The moderation screens over HTTP, with an administrator signed in: every change needs the
 * session token and leaves a line in the administration log.
 */
final class ModerationScreensTest extends WebIntegrationTestCase
{
    private const LIST_URL = '/admin/module/ProductQuestion';

    private AdminSessionInjector $injector;

    private FixtureFactory $factory;

    private ProductQuestion $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);

        // Not createFixtureFactory(): the bare request it pushes would become the main request the
        // security context reads its session from.
        $this->factory = new FixtureFactory($this->getPropelConnection());
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $this->question = (new ProductQuestion())
            ->setProductId((int) $product->getId())
            ->setLocale('en_US')
            ->setContent('Moderation screen question?')
            ->setStatusEnum(ProductQuestionStatus::Pending);
        $this->question->save();

        $admin = $this->factory->admin();
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
    }

    protected function tearDown(): void
    {
        $this->injector->setAdmin(null);
        $this->getService(EventDispatcherInterface::class)->removeSubscriber($this->injector);

        parent::tearDown();
    }

    public function testPublishingAQuestionWithoutAnsweringNeedsTheTokenAndIsLogged(): void
    {
        $this->client->request('POST', self::LIST_URL.'/'.$this->question->getId().'/publish', ['_token' => 'forged']);

        self::assertSame(ProductQuestionStatus::Pending, $this->storedQuestion()?->getStatusEnum(), 'A forged token changes nothing.');

        $this->client->request('POST', self::LIST_URL.'/'.$this->question->getId().'/publish', ['_token' => $this->tokenOfTheEditScreen()]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame(ProductQuestionStatus::Published, $this->storedQuestion()?->getStatusEnum());
        self::assertSame(1, $this->logLines());
    }

    public function testACustomerAnswerIsListedAndModerated(): void
    {
        $this->question->setStatusEnum(ProductQuestionStatus::Published)->save();
        $answer = (new ProductQuestionAnswer())
            ->setQuestionId((int) $this->question->getId())
            ->setContent('Customer answer under moderation.')
            ->setStatusEnum(ProductQuestionStatus::Pending);
        $answer->save();

        $crawler = $this->client->request('GET', self::LIST_URL.'?answers=pending&product_id='.$this->question->getProductId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('[data-testid="product-question-row-pending-answers-'.$this->question->getId().'"]'));

        $crawler = $this->client->request('GET', self::LIST_URL.'/'.$this->question->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('Customer answer under moderation.', $crawler->filter('[data-testid="product-question-customer-answer-'.$answer->getId().'"]')->text());

        $form = $crawler->filter('[data-testid="product-question-answer-publish-'.$answer->getId().'"]')->closest('form');
        $this->client->request('POST', (string) $form->attr('action'), ['_token' => $form->filter('input[name="_token"]')->attr('value')]);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        $stored = ProductQuestionAnswerQuery::create()->findPk($answer->getId());
        self::assertInstanceOf(ProductQuestionAnswer::class, $stored);
        self::assertSame(ProductQuestionStatus::Published, $stored->getStatusEnum());
        self::assertNotNull($stored->getPublishedAt());
        self::assertSame(1, $this->logLines());
    }

    public function testTheOfficialAnswerPublishesTheQuestionAndIsLogged(): void
    {
        $crawler = $this->client->request('GET', self::LIST_URL.'/'.$this->question->getId());
        $form = $crawler->filter('[data-testid="product-question-answer-form"]')->form();
        $form['productquestion_answer[answer]'] = 'The shop answers.';
        $this->client->submit($form);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame(ProductQuestionStatus::Published, $this->storedQuestion()?->getStatusEnum());
        self::assertSame('The shop answers.', ProductQuestionAnswerQuery::create()->filterByQuestionId($this->question->getId())->filterByIsOfficial(true)->findOne()?->getContent());
        self::assertSame(1, $this->logLines());
    }

    public function testRefusingAndDeletingAreLogged(): void
    {
        $this->client->request('POST', self::LIST_URL.'/'.$this->question->getId().'/refuse', ['_token' => $this->tokenOfTheEditScreen()]);
        self::assertSame(ProductQuestionStatus::Refused, $this->storedQuestion()?->getStatusEnum());

        $this->client->request('POST', self::LIST_URL.'/'.$this->question->getId().'/delete', ['_token' => $this->tokenOfTheEditScreen()]);
        self::assertNull($this->storedQuestion());
        self::assertSame(2, $this->logLines());
    }

    public function testTheShopOpensCustomerAnswersFromTheListScreen(): void
    {
        $crawler = $this->client->request('GET', self::LIST_URL);
        $token = $crawler->filter('[data-testid="product-question-settings"] input[name="_token"]')->attr('value');

        $this->client->request('POST', self::LIST_URL.'/settings', ['_token' => $token, 'allow_customer_answers' => '1']);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertTrue((new ModuleConfigProductQuestionSettings())->allowsCustomerAnswers());
        self::assertSame(1, AdminLogQuery::create()->filterByResource('ProductQuestion')->filterByMessage('%settings%', \Propel\Runtime\ActiveQuery\Criteria::LIKE)->count());
    }

    /**
     * The list shows who asked, and leaves the questions it read as they were: a question nobody
     * owns any more is saved afterwards without breaking its foreign key.
     */
    public function testTheListNamesTheCustomerAndLeavesTheQuestionsUntouched(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle(), ['firstname' => 'Kenji', 'lastname' => 'Asker']);
        $owned = (new ProductQuestion())
            ->setProductId((int) $this->question->getProductId())
            ->setCustomerId((int) $customer->getId())
            ->setLocale('en_US')
            ->setContent('Owned question?')
            ->setStatusEnum(ProductQuestionStatus::Pending);
        $owned->save();

        $crawler = $this->client->request('GET', self::LIST_URL.'?product_id='.$this->question->getProductId());

        self::assertStringContainsString('Kenji Asker', $crawler->filter('[data-testid="product-question-row-'.$owned->getId().'"]')->text());
        self::assertNull($this->question->getCustomerId(), 'The list gave the question nobody owns a customer of its own.');

        $this->question->setContent('Edited after the list was drawn?')->save();
        self::assertNull(ProductQuestionQuery::create()->findPk($this->question->getId())?->getCustomerId());
    }

    /**
     * The menu entry counts what waits for a moderator: questions and customer answers alike.
     */
    public function testTheMenuShowsHowManyItemsWaitForAModerator(): void
    {
        $before = $this->menuCount();

        $answer = (new ProductQuestionAnswer())
            ->setQuestionId((int) $this->question->getId())
            ->setContent('Customer answer under moderation.')
            ->setStatusEnum(ProductQuestionStatus::Pending);
        $answer->save();

        self::assertSame($before + 1, $this->menuCount());

        // Written by query: the instances went through the list page, whose join hydrated them.
        ProductQuestionQuery::create()->filterById($this->question->getId())->update(['Status' => ProductQuestionStatus::Published->value]);
        ProductQuestionAnswerQuery::create()->filterById($answer->getId())->update(['Status' => ProductQuestionStatus::Published->value]);

        self::assertSame($before - 1, $this->menuCount());
    }

    private function menuCount(): int
    {
        $crawler = $this->client->request('GET', self::LIST_URL);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('[data-testid="bo-nav-product-question"]'));

        $badge = $crawler->filter('[data-testid="bo-nav-product-question-pending"]');

        return 0 === $badge->count() ? 0 : (int) $badge->text();
    }

    private function tokenOfTheEditScreen(): string
    {
        $crawler = $this->client->request('GET', self::LIST_URL.'/'.$this->question->getId());

        return (string) $crawler->filter('[data-testid="product-question-refuse"]')->closest('form')->filter('input[name="_token"]')->attr('value');
    }

    private function storedQuestion(): ?ProductQuestion
    {
        return ProductQuestionQuery::create()->findPk($this->question->getId());
    }

    private function logLines(): int
    {
        return AdminLogQuery::create()->filterByResource('ProductQuestion')->filterByResourceId($this->question->getId())->count();
    }
}
