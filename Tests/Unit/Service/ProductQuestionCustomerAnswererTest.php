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

namespace ProductQuestion\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use ProductQuestion\Exception\InvalidProductQuestionException;
use ProductQuestion\Exception\ProductQuestionsClosedException;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\Front\ProductQuestionTextSanitizer;
use ProductQuestion\Service\ProductQuestionAvailability;
use ProductQuestion\Service\ProductQuestionCustomerAnswerer;
use ProductQuestion\Tests\Double\FixedSettings;
use ProductQuestion\Tests\Double\InMemoryClosedProducts;
use ProductQuestion\Tests\Double\InMemoryProductQuestionAnswerStorage;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use ProductQuestion\Tests\Double\InMemoryProductVisibility;

/**
 * A customer's answer to someone's question: only when the shop takes them, only to a question
 * a visitor can read, and always waiting for a moderator.
 */
final class ProductQuestionCustomerAnswererTest extends TestCase
{
    private InMemoryProductQuestionAnswerStorage $answers;

    private FixedSettings $settings;

    private InMemoryClosedProducts $closedProducts;

    private ProductQuestionCustomerAnswerer $answerer;

    protected function setUp(): void
    {
        $this->answers = new InMemoryProductQuestionAnswerStorage();
        $this->settings = new FixedSettings(customerAnswers: true);
        $this->closedProducts = new InMemoryClosedProducts();
        $this->answerer = new ProductQuestionCustomerAnswerer(
            new InMemoryProductQuestionStorage([
                $this->question(1, 12, ProductQuestionStatus::Published),
                $this->question(2, 12, ProductQuestionStatus::Pending),
                $this->question(3, 12, ProductQuestionStatus::Refused),
                $this->question(4, 99, ProductQuestionStatus::Published),
            ]),
            $this->answers,
            new ProductQuestionTextSanitizer(),
            new InMemoryProductVisibility([12]),
            $this->settings,
            new ProductQuestionAvailability($this->settings, $this->closedProducts),
        );
    }

    private function question(int $id, int $productId, ProductQuestionStatus $status): ProductQuestion
    {
        return (new ProductQuestion())
            ->setId($id)
            ->setProductId($productId)
            ->setCustomerId(34)
            ->setLocale('fr_FR')
            ->setContent('Q ?')
            ->setStatusEnum($status);
    }

    public function testTheAnswerIsStoredPendingCleanedAndSignedByTheCustomer(): void
    {
        $answer = $this->answerer->answer(1, 56, '  Oui, <b>chez moi</b> ça marche.  ');

        self::assertSame(ProductQuestionStatus::Pending, $answer->getStatusEnum());
        self::assertSame('Oui, chez moi ça marche.', $answer->getContent());
        self::assertSame(56, $answer->getCustomerId());
        self::assertNull($answer->getAdminId());
        self::assertFalse($answer->isOfficialAnswer());
        self::assertNull($answer->getPublishedAt());
        self::assertSame([$answer], $this->answers->saved);
    }

    public function testNothingIsTakenWhileTheShopKeepsCustomerAnswersClosed(): void
    {
        $this->settings->customerAnswers = false;

        $this->expectExceptionObject(InvalidProductQuestionException::customerAnswersClosed());

        try {
            $this->answerer->answer(1, 56, 'Oui.');
        } finally {
            self::assertSame([], $this->answers->saved);
        }
    }

    /**
     * A question waiting for the shop, refused, or about an offline product is not readable by a
     * visitor, so not one to answer.
     */
    public function testOnlyAQuestionAVisitorCanReadIsAnswerable(): void
    {
        foreach ([2, 3, 4, 999] as $questionId) {
            try {
                $this->answerer->answer($questionId, 56, 'Oui.');
                self::fail('Question '.$questionId.' must not be answerable.');
            } catch (InvalidProductQuestionException $exception) {
                self::assertSame(InvalidProductQuestionException::unknownQuestion()->getMessage(), $exception->getMessage());
            }
        }

        self::assertSame([], $this->answers->saved);
    }

    public function testAnAnswerThatCleansDownToNothingIsRefused(): void
    {
        $this->expectException(InvalidProductQuestionException::class);

        $this->answerer->answer(1, 56, '<p> </p>');
    }

    public function testATooLongAnswerIsRefused(): void
    {
        $this->expectException(InvalidProductQuestionException::class);

        $this->answerer->answer(1, 56, str_repeat('a', 5001));
    }

    public function testAnAnonymousVisitorCannotAnswer(): void
    {
        $this->expectException(InvalidProductQuestionException::class);

        $this->answerer->answer(1, 0, 'Oui.');
    }

    /**
     * A closed product is read-only: its published questions take no new answer.
     */
    public function testAnAnswerToAQuestionOfAClosedProductIsRefused(): void
    {
        $this->closedProducts->setClosed(12, true);

        $this->expectException(ProductQuestionsClosedException::class);

        try {
            $this->answerer->answer(1, 55, 'Yes, it folds flat.');
        } finally {
            self::assertSame([], $this->answers->saved);
        }
    }

    public function testAnAnswerIsRefusedWhileTheWholeShopIsClosed(): void
    {
        $this->settings->setQuestionsClosed(true);

        $this->expectException(ProductQuestionsClosedException::class);

        $this->answerer->answer(1, 55, 'Yes, it folds flat.');
    }
}
