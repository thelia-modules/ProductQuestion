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
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\ProductQuestionHelpfulVoter;
use ProductQuestion\Tests\Double\InMemoryProductQuestionAnswerStorage;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use ProductQuestion\Tests\Double\InMemoryProductVisibility;

final class ProductQuestionHelpfulVoterTest extends TestCase
{
    private InMemoryProductQuestionAnswerStorage $answers;

    private ProductQuestionHelpfulVoter $voter;

    protected function setUp(): void
    {
        $this->answers = new InMemoryProductQuestionAnswerStorage([
            $this->answer(1, 1, ProductQuestionStatus::Published, 56),
            $this->answer(2, 1, ProductQuestionStatus::Pending, 57),
            $this->answer(3, 2, ProductQuestionStatus::Published, 58),
            $this->answer(4, 3, ProductQuestionStatus::Published, 59),
        ]);
        $this->voter = new ProductQuestionHelpfulVoter(
            new InMemoryProductQuestionStorage([
                (new ProductQuestion())->setId(1)->setProductId(12)->setLocale('fr_FR')->setContent('Q ?')->setStatusEnum(ProductQuestionStatus::Published),
                (new ProductQuestion())->setId(2)->setProductId(12)->setLocale('fr_FR')->setContent('Q ?')->setStatusEnum(ProductQuestionStatus::Refused),
                (new ProductQuestion())->setId(3)->setProductId(99)->setLocale('fr_FR')->setContent('Q ?')->setStatusEnum(ProductQuestionStatus::Published),
            ]),
            $this->answers,
            new InMemoryProductVisibility([12]),
        );
    }

    private function answer(int $id, int $questionId, ProductQuestionStatus $status, int $customerId): ProductQuestionAnswer
    {
        return (new ProductQuestionAnswer())->setId($id)->setQuestionId($questionId)->setCustomerId($customerId)->setContent('R')->setStatusEnum($status);
    }

    public function testAVoteIsCountedOncePerCustomer(): void
    {
        self::assertTrue($this->voter->vote(1, 34));
        self::assertFalse($this->voter->vote(1, 34));
        self::assertTrue($this->voter->vote(1, 35));

        self::assertSame(2, $this->answers->findById(1)?->getHelpfulCount());
    }

    /**
     * An answer a visitor cannot read, pending, under a refused question or about an offline
     * product, takes no vote.
     */
    public function testOnlyAReadableAnswerTakesAVote(): void
    {
        foreach ([2, 3, 4, 999] as $answerId) {
            try {
                $this->voter->vote($answerId, 34);
                self::fail('Answer '.$answerId.' must not take a vote.');
            } catch (InvalidProductQuestionException) {
                // Expected.
            }
        }

        self::assertSame([], $this->answers->votes);
    }

    public function testNobodyVotesForTheirOwnAnswer(): void
    {
        $this->expectExceptionObject(InvalidProductQuestionException::ownAnswer());

        $this->voter->vote(1, 56);
    }

    public function testAnAnonymousVisitorDoesNotVote(): void
    {
        $this->expectException(InvalidProductQuestionException::class);

        $this->voter->vote(1, 0);
    }
}
