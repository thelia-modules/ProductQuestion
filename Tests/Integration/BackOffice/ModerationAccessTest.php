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
use ProductQuestion\Model\ProductQuestionQuery;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\ProductQuestion as ProductQuestionModule;
use ProductQuestion\Tests\Double\AdminSessionInjector;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\ProfileModule;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;

/**
 * Moderation is granted on the module: an administrator whose profile does not give them the
 * module, or gives it for viewing only, changes nothing, even with a valid session token.
 */
final class ModerationAccessTest extends WebIntegrationTestCase
{
    private const LIST_URL = '/admin/module/ProductQuestion';

    private AdminSessionInjector $injector;

    private FixtureFactory $factory;

    private ProductQuestion $question;

    private Admin $superAdministrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);

        $this->factory = new FixtureFactory($this->getPropelConnection());
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $this->question = (new ProductQuestion())
            ->setProductId((int) $product->getId())
            ->setLocale('en_US')
            ->setContent('Question under a restricted moderator?')
            ->setStatusEnum(ProductQuestionStatus::Pending);
        $this->question->save();

        $this->superAdministrator = $this->factory->admin();
        $this->superAdministrator->eraseCredentials();
        $this->injector->setAdmin($this->superAdministrator);
    }

    protected function tearDown(): void
    {
        $this->injector->setAdmin(null);
        $this->getService(EventDispatcherInterface::class)->removeSubscriber($this->injector);

        parent::tearDown();
    }

    public function testAnAdministratorWithoutTheModuleSeesNothingAndChangesNothing(): void
    {
        $token = $this->tokenOfTheEditScreen();
        $this->signIn($this->factory->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::VIEW, AccessManager::UPDATE, AccessManager::DELETE]]));

        $this->client->request('GET', self::LIST_URL);
        self::assertSame(403, $this->client->getResponse()->getStatusCode(), 'The list is not shown to an administrator without the module.');

        $this->client->request('GET', self::LIST_URL.'/'.$this->question->getId());
        self::assertSame(403, $this->client->getResponse()->getStatusCode(), 'The question is not shown to an administrator without the module.');

        foreach (['publish', 'refuse', 'delete'] as $decision) {
            $this->client->request('POST', self::LIST_URL.'/'.$this->question->getId().'/'.$decision, ['_token' => $token]);

            self::assertSame(403, $this->client->getResponse()->getStatusCode(), \sprintf('"%s" is refused to an administrator without the module.', $decision));
            self::assertSame(ProductQuestionStatus::Pending, $this->storedQuestion()?->getStatusEnum(), \sprintf('"%s" by an administrator without the module changes nothing.', $decision));
        }

        self::assertSame(0, $this->logLines());
    }

    public function testAModeratorWhoMayOnlyViewTheModuleChangesNothing(): void
    {
        $token = $this->tokenOfTheEditScreen();
        $this->signIn($this->viewOnlyModerator());

        $this->client->request('GET', self::LIST_URL.'/'.$this->question->getId());
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'Viewing is what the profile grants.');

        foreach (['publish', 'refuse', 'delete'] as $decision) {
            $this->client->request('POST', self::LIST_URL.'/'.$this->question->getId().'/'.$decision, ['_token' => $token]);

            self::assertSame(403, $this->client->getResponse()->getStatusCode(), \sprintf('"%s" is refused to a view-only moderator.', $decision));
            self::assertSame(ProductQuestionStatus::Pending, $this->storedQuestion()?->getStatusEnum(), \sprintf('"%s" by a view-only moderator changes nothing.', $decision));
        }

        self::assertSame(0, $this->logLines());
    }

    private function viewOnlyModerator(): Admin
    {
        $profile = $this->factory->profile();
        $module = ModuleQuery::create()->findOneByCode(ProductQuestionModule::getModuleCode());
        self::assertNotNull($module, 'The module is installed in the test database.');

        $access = new AccessManager(0);
        $access->build([AccessManager::VIEW]);

        (new ProfileModule())
            ->setProfileId((int) $profile->getId())
            ->setModuleId((int) $module->getId())
            ->setAccess($access->getAccessValue())
            ->save();

        return $this->factory->admin(['profile' => $profile]);
    }

    private function signIn(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);
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
