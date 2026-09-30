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
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\ProductQuestion as ProductQuestionModule;
use ProductQuestion\Service\Notification\ProductQuestionAnswerNotification;
use ProductQuestion\Service\Notification\ProductQuestionUnsubscribeLink;
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
            ->setStatusEnum(ProductQuestionStatus::Published);

        return $question;
    }

    private function answered(ProductQuestion $question, bool $first, bool $official = true, ?int $authorId = null): ProductQuestionAnsweredEvent
    {
        $answer = (new ProductQuestionAnswer())
            ->setId(8)
            ->setQuestionId($question->getId())
            ->setIsOfficial($official)
            ->setAdminId($official ? 7 : null)
            ->setCustomerId($authorId)
            ->setContent('Oui, compatible.')
            ->setStatusEnum(ProductQuestionStatus::Published);

        return new ProductQuestionAnsweredEvent($question, $first, $answer);
    }

    private function notifier(SpyCustomerMailer $mailer, ?SpyLogger $logger = null): ProductQuestionCustomerNotifier
    {
        return new ProductQuestionCustomerNotifier(
            $mailer,
            new ProductQuestionAnswerNotification(new FixedTranslator()),
            new InMemoryProductTitles(['fr_FR' => [12 => 'Horatio'], 'en_US' => [12 => 'Horatio EN']]),
            new FixedShopContext('en_US'),
            $logger ?? new SpyLogger(),
            new ProductQuestionUnsubscribeLink('test-secret', new FixedShopContext('en_US')),
        );
    }

    public function testTheCustomerIsToldInTheLanguageTheyAskedInWithTheProductPage(): void
    {
        $mailer = new SpyCustomerMailer();

        $this->notifier($mailer)->onQuestionAnswered($this->answered($this->question(), true));

        self::assertCount(1, $mailer->sent);
        $sent = $mailer->sent[0];
        self::assertSame(ProductQuestionModule::MESSAGE_CUSTOMER_ANSWERED, $sent['code']);
        self::assertSame(42, $sent['customerId']);
        // The question's language, not the shop's.
        self::assertSame('fr_FR', $sent['locale']);
        self::assertSame('Horatio', $sent['parameters']['question']['productTitle']);
        self::assertSame('https://shop.test/fr_FR/product-12.html', $sent['parameters']['question']['productUrl']);
        self::assertSame('Oui, compatible.', $sent['parameters']['question']['answer']);
        // Signed and dated, pointing at this question.
        self::assertMatchesRegularExpression('#^https://shop\.test/product-question/5/unsubscribe\?expires=\d+&signature=[0-9a-f]{64}$#', $sent['parameters']['question']['unsubscribeUrl']);
    }

    /**
     * Another customer's answer goes out under its own message, so its subject does not speak
     * for the shop.
     */
    public function testACustomerAnswerIsAnnouncedWithItsOwnMessage(): void
    {
        $mailer = new SpyCustomerMailer();

        $this->notifier($mailer)->onQuestionAnswered($this->answered($this->question(), true, false, 56));

        self::assertSame(ProductQuestionModule::MESSAGE_CUSTOMER_ANSWERED_BY_CUSTOMER, $mailer->sent[0]['code'] ?? null);
    }

    public function testAnAuthorWhoUnsubscribedIsNotWrittenTo(): void
    {
        $mailer = new SpyCustomerMailer();
        $question = $this->question()->setNotifyAuthor(false);

        $this->notifier($mailer)->onQuestionAnswered($this->answered($question, true));

        self::assertSame([], $mailer->sent);
    }

    public function testAnAuthorAnsweringTheirOwnQuestionIsNotToldAboutIt(): void
    {
        $mailer = new SpyCustomerMailer();

        $this->notifier($mailer)->onQuestionAnswered($this->answered($this->question(42), true, false, 42));

        self::assertSame([], $mailer->sent);
    }

    /**
     * An event dispatched the 1.2.0 way, without the answer row, has nothing to announce.
     */
    public function testAnEventWithoutAnswerSendsNothing(): void
    {
        $mailer = new SpyCustomerMailer();

        $this->notifier($mailer)->onQuestionAnswered(new ProductQuestionAnsweredEvent($this->question(), true));

        self::assertSame([], $mailer->sent);
    }

    public function testAnEditedAnswerIsNotAnnouncedAgain(): void
    {
        $mailer = new SpyCustomerMailer();

        $this->notifier($mailer)->onQuestionAnswered($this->answered($this->question(), false));

        self::assertSame([], $mailer->sent);
    }

    public function testADeletedAccountHasNobodyToWriteTo(): void
    {
        $mailer = new SpyCustomerMailer();

        $this->notifier($mailer)->onQuestionAnswered($this->answered($this->question(null), true));

        self::assertSame([], $mailer->sent);
    }

    public function testAMailThatCannotLeaveIsLoggedAndNeverThrown(): void
    {
        $logger = new SpyLogger();

        $this->notifier(new SpyCustomerMailer(new \RuntimeException('no transport')), $logger)
            ->onQuestionAnswered($this->answered($this->question(), true));

        self::assertCount(1, $logger->records);
        self::assertStringContainsString('question 5', $logger->records[0]['message']);
        self::assertStringContainsString('no transport', $logger->records[0]['message']);
    }

    /**
     * An SMTP refusal quotes the dialogue with the server, recipient included: the log keeps the
     * reason and loses the customer's address.
     */
    public function testTheLoggedFailureCarriesNoAddress(): void
    {
        $logger = new SpyLogger();
        $refusal = new \RuntimeException('Expected response code "250" but got code "550", with message "550 5.1.1 <ada.lovelace@example.com>: Recipient address rejected".');

        $this->notifier(new SpyCustomerMailer($refusal), $logger)
            ->onQuestionAnswered($this->answered($this->question(), true));

        self::assertCount(1, $logger->records);
        self::assertStringContainsString('Recipient address rejected', $logger->records[0]['message']);
        self::assertStringNotContainsString('ada.lovelace', $logger->records[0]['message']);
        self::assertStringNotContainsString('@example.com', $logger->records[0]['message']);
    }
}
