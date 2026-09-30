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

namespace ProductQuestion\Tests\Integration;

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionAnswerQuery;
use ProductQuestion\Model\ProductQuestionAnswerVoteQuery;
use ProductQuestion\Model\ProductQuestionQuery;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ProductQuestionAnswerRepository;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;

/**
 * Deleting a product through Thelia takes its questions, their answers and their votes with it,
 * and leaves the ones of every other product in place.
 */
final class ProductDeletionTest extends IntegrationTestCase
{
    public function testDeletingAProductDeletesItsQuestionsAnswersAndVotesOnly(): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $voter = $factory->customer($factory->customerTitle());
        $deleted = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $kept = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        $this->questionWithAnswersAndVotes($deleted, $customer, $voter);
        $this->questionWithAnswersAndVotes($deleted, $customer, $voter);
        $this->questionWithAnswersAndVotes($kept, $customer, $voter);

        $before = $this->rowCounts();

        $deleted->delete();

        self::assertSame([
            'questions' => $before['questions'] - 2,
            'answers' => $before['answers'] - 4,
            'votes' => $before['votes'] - 8,
        ], $this->rowCounts());
        self::assertSame(0, ProductQuestionQuery::create()->filterByProductId((int) $deleted->getId())->count());
        self::assertSame(1, ProductQuestionQuery::create()->filterByProductId((int) $kept->getId())->count());
    }

    /**
     * One question, a shop answer and a customer answer, each voted for twice.
     */
    private function questionWithAnswersAndVotes(Product $product, Customer $customer, Customer $voter): void
    {
        $question = (new ProductQuestion())
            ->setProductId((int) $product->getId())
            ->setCustomerId((int) $customer->getId())
            ->setLocale('en_US')
            ->setContent('Does it fit?')
            ->setStatusEnum(ProductQuestionStatus::Published);
        $question->save();

        $repository = new ProductQuestionAnswerRepository();

        foreach ([true, false] as $official) {
            $answer = (new ProductQuestionAnswer())
                ->setQuestionId((int) $question->getId())
                ->setIsOfficial($official)
                ->setCustomerId($official ? null : (int) $customer->getId())
                ->setContent('It does.')
                ->setHelpfulCount(0)
                ->setStatusEnum(ProductQuestionStatus::Published);
            $answer->save();

            $repository->addVote((int) $answer->getId(), (int) $customer->getId());
            $repository->addVote((int) $answer->getId(), (int) $voter->getId());
        }
    }

    /**
     * @return array{questions: int, answers: int, votes: int}
     */
    private function rowCounts(): array
    {
        return [
            'questions' => ProductQuestionQuery::create()->count(),
            'answers' => ProductQuestionAnswerQuery::create()->count(),
            'votes' => ProductQuestionAnswerVoteQuery::create()->count(),
        ];
    }
}
