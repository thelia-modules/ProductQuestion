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

namespace ProductQuestion\Tests\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use ProductQuestion\Event\ProductQuestionCreatedEvent;
use ProductQuestion\EventListener\ProductQuestionNotifier;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\ProductQuestion as ProductQuestionModule;
use ProductQuestion\Service\Notification\ProductQuestionNotification;
use ProductQuestion\Tests\Double\FixedShopContext;
use ProductQuestion\Tests\Double\FixedTranslator;
use ProductQuestion\Tests\Double\InMemoryProductTitles;
use ProductQuestion\Tests\Double\SpyLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Mailer\MailerFactory;

/**
 * The mail the shop gets when a customer asks, and the one rule around it: it never takes
 * the question down with it.
 */
final class ProductQuestionNotifierTest extends TestCase
{
    private function question(int $id = 5): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setId($id)
            ->setProductId(12)
            ->setCustomerId(42)
            ->setLocale('en_US')
            ->setContent('Does it fit a 60 cm shelf?')
            ->setStatusEnum(ProductQuestionStatus::Pending);

        return $question;
    }

    public function testTheShopIsToldInItsOwnLanguageWithALinkToTheQuestion(): void
    {
        $mailer = $this->createMock(MailerFactory::class);
        $sent = null;
        $mailer->expects(self::once())
            ->method('sendEmailToShopManagers')
            ->willReturnCallback(static function (string $code, array $parameters) use (&$sent): void {
                $sent = [$code, $parameters];
            });

        $notifier = new ProductQuestionNotifier(
            $mailer,
            new ProductQuestionNotification(new FixedTranslator()),
            new InMemoryProductTitles(['fr_FR' => [12 => 'Horatio'], 'en_US' => [12 => 'Horatio EN']]),
            new FixedShopContext('fr_FR'),
            new SpyLogger(),
        );

        $notifier->onQuestionCreated(new ProductQuestionCreatedEvent($this->question()));

        self::assertNotNull($sent);
        self::assertSame(ProductQuestionModule::MESSAGE_ADMIN_NOTIFICATION, $sent[0]);
        self::assertSame('Does it fit a 60 cm shelf?', $sent[1]['question']['content']);
        // The product is named in the shop's language, whatever the question was asked in.
        self::assertSame('Horatio', $sent[1]['question']['productTitle']);
        self::assertSame('https://shop.test/admin/module/ProductQuestion/5', $sent[1]['question']['adminUrl']);
        self::assertArrayNotHasKey('customerId', $sent[1]['question']);
    }

    /**
     * The listener runs inside the dispatch that follows the save: the customer has asked, the
     * row is there, and a mail the shop cannot receive is the shop's problem, not theirs.
     */
    public function testAMailThatCannotLeaveIsLoggedAndNeverThrown(): void
    {
        $mailer = $this->createMock(MailerFactory::class);
        $mailer->method('sendEmailToShopManagers')->willThrowException(new \RuntimeException('no transport'));
        $logger = new SpyLogger();

        $notifier = new ProductQuestionNotifier(
            $mailer,
            new ProductQuestionNotification(new FixedTranslator()),
            new InMemoryProductTitles([]),
            new FixedShopContext(),
            $logger,
        );

        $notifier->onQuestionCreated(new ProductQuestionCreatedEvent($this->question()));

        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertStringContainsString('question 5', $logger->records[0]['message']);
        self::assertStringContainsString('no transport', $logger->records[0]['message']);
    }

    public function testTheListenerIsWiredOnTheCreatedEvent(): void
    {
        $mailer = $this->createMock(MailerFactory::class);
        $mailer->expects(self::once())->method('sendEmailToShopManagers');

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ProductQuestionNotifier(
            $mailer,
            new ProductQuestionNotification(new FixedTranslator()),
            new InMemoryProductTitles([]),
            new FixedShopContext(),
            new SpyLogger(),
        ));

        $dispatcher->dispatch(new ProductQuestionCreatedEvent($this->question()));
    }

    public function testAQuestionWithoutAnIdIsNotAnnounced(): void
    {
        $mailer = $this->createMock(MailerFactory::class);
        $mailer->expects(self::never())->method('sendEmailToShopManagers');

        $notifier = new ProductQuestionNotifier($mailer, new ProductQuestionNotification(new FixedTranslator()), new InMemoryProductTitles([]), new FixedShopContext(), new SpyLogger());

        $notifier->onQuestionCreated(new ProductQuestionCreatedEvent((new ProductQuestion())->setContent('Q ?')));
    }
}
