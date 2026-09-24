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
use ProductQuestion\Event\ProductQuestionAnsweredEvent;
use ProductQuestion\EventListener\ProductQuestionCustomerNotifier;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\ProductQuestion as ProductQuestionModule;
use ProductQuestion\Service\Notification\ProductQuestionAnswerNotification;
use ProductQuestion\Tests\Double\FixedShopContext;
use ProductQuestion\Tests\Double\FixedTranslator;
use ProductQuestion\Tests\Double\InMemoryProductTitles;
use ProductQuestion\Tests\Double\SpyCustomerMailer;
use ProductQuestion\Tests\Double\SpyLogger;

/**
 * The mail a customer gets when the shop answers: once, in their language, and never a reason
 * for the moderator's action to fail.
 */
final class ProductQuestionCustomerNotifierTest extends TestCase
{
    private function question(?int $customerId = 42): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setId(5)
            ->setProductId(12)
            ->setCustomerId($customerId)
            ->setLocale('fr_FR')
            ->setContent('Est-ce compatible ?')
            ->setAnswer('Oui, compatible.')
            ->setStatusEnum(ProductQuestionStatus::Answered);

        return $question;
    }

    private function notifier(SpyCustomerMailer $mailer, ?SpyLogger $logger = null): ProductQuestionCustomerNotifier
    {
        return new ProductQuestionCustomerNotifier(
            $mailer,
            new ProductQuestionAnswerNotification(new FixedTranslator()),
            new InMemoryProductTitles(['fr_FR' => [12 => 'Horatio'], 'en_US' => [12 => 'Horatio EN']]),
            new FixedShopContext('en_US'),
            $logger ?? new SpyLogger(),
        );
    }

    public function testTheCustomerIsToldInTheLanguageTheyAskedInWithTheProductPage(): void
    {
        $mailer = new SpyCustomerMailer();

        $this->notifier($mailer)->onQuestionAnswered(new ProductQuestionAnsweredEvent($this->question(), true));

        self::assertCount(1, $mailer->sent);
        $sent = $mailer->sent[0];
        self::assertSame(ProductQuestionModule::MESSAGE_CUSTOMER_ANSWERED, $sent['code']);
        self::assertSame(42, $sent['customerId']);
        // The question's language, not the shop's.
        self::assertSame('fr_FR', $sent['locale']);
        self::assertSame('Horatio', $sent['parameters']['question']['productTitle']);
        self::assertSame('https://shop.test/fr_FR/product-12.html', $sent['parameters']['question']['productUrl']);
        self::assertSame('Oui, compatible.', $sent['parameters']['question']['answer']);
    }

    public function testAnEditedAnswerIsNotAnnouncedAgain(): void
    {
        $mailer = new SpyCustomerMailer();

        $this->notifier($mailer)->onQuestionAnswered(new ProductQuestionAnsweredEvent($this->question(), false));

        self::assertSame([], $mailer->sent);
    }

    public function testADeletedAccountHasNobodyToWriteTo(): void
    {
        $mailer = new SpyCustomerMailer();

        $this->notifier($mailer)->onQuestionAnswered(new ProductQuestionAnsweredEvent($this->question(null), true));

        self::assertSame([], $mailer->sent);
    }

    public function testAMailThatCannotLeaveIsLoggedAndNeverThrown(): void
    {
        $logger = new SpyLogger();

        $this->notifier(new SpyCustomerMailer(new \RuntimeException('no transport')), $logger)
            ->onQuestionAnswered(new ProductQuestionAnsweredEvent($this->question(), true));

        self::assertCount(1, $logger->records);
        self::assertStringContainsString('question 5', $logger->records[0]['message']);
        self::assertStringContainsString('no transport', $logger->records[0]['message']);
    }
}
