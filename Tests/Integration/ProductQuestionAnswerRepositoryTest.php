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
use ProductQuestion\Model\ProductQuestionQuery;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ProductQuestionAnswerRepository;
use ProductQuestion\Repository\ProductQuestionRepository;
use ProductQuestion\Service\BackOffice\ProductQuestionListFilters;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;

/**
 * The Propel queries behind the answer storage, on the test database.
 */
final class ProductQuestionAnswerRepositoryTest extends IntegrationTestCase
{
    private ProductQuestionAnswerRepository $answers;

    private Product $product;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->customer = $factory->customer($factory->customerTitle());
        $this->product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $this->answers = new ProductQuestionAnswerRepository();
    }

    public function testThePublishedAnswersOfAPageComeInOneQueryShopFirstThenMostHelpful(): void
    {
        $first = $this->question('First?');
        $second = $this->question('Second?');
        $this->answer($first, 'customer, 1 vote', ProductQuestionStatus::Published, false, 1);
        $this->answer($first, 'shop', ProductQuestionStatus::Published, true, 0);
        $this->answer($first, 'customer, 4 votes', ProductQuestionStatus::Published, false, 4);
        $this->answer($first, 'pending', ProductQuestionStatus::Pending);
        $this->answer($second, 'refused', ProductQuestionStatus::Refused);

        $byQuestion = $this->answers->findPublishedForQuestions([(int) $first->getId(), (int) $second->getId()]);

        self::assertSame(
            ['shop', 'customer, 4 votes', 'customer, 1 vote'],
            array_map(static fn (ProductQuestionAnswer $answer): ?string => $answer->getContent(), $byQuestion[(int) $first->getId()]),
        );
        self::assertArrayNotHasKey((int) $second->getId(), $byQuestion);
    }

    public function testTheOfficialAnswerAndThePendingCountsAreRead(): void
    {
        $question = $this->question('Q?');
        $this->answer($question, 'customer', ProductQuestionStatus::Pending);
        $this->answer($question, 'customer 2', ProductQuestionStatus::Pending);
        $official = $this->answer($question, 'shop', ProductQuestionStatus::Published, true);

        self::assertSame($official->getId(), $this->answers->findOfficialForQuestion((int) $question->getId())?->getId());
        self::assertSame([(int) $question->getId() => 2], $this->answers->countPendingByQuestion([(int) $question->getId()]));
        self::assertGreaterThanOrEqual(2, $this->answers->countPending());
        self::assertSame('shop', $this->answers->findForQuestion((int) $question->getId())[0]->getContent());
    }

    /**
     * The question's counter is the sum of its published answers' votes: a refused answer's
     * votes stop counting.
     */
    public function testTheQuestionCounterSumsThePublishedAnswersOnly(): void
    {
        $question = $this->question('Q?');
        $this->answer($question, 'a', ProductQuestionStatus::Published, false, 3);
        $this->answer($question, 'b', ProductQuestionStatus::Refused, false, 5);

        $this->answers->refreshQuestionHelpfulCount((int) $question->getId());

        self::assertSame(3, ProductQuestionQuery::create()->findPk($question->getId())?->getHelpfulCount());
    }

    /**
     * The product page lists the most helpful questions first, and the moderation list can be
     * narrowed to the questions with a customer answer waiting.
     */
    public function testQuestionsAreOrderedByUsefulnessAndFilterableByPendingAnswers(): void
    {
        $plain = $this->question('Plain?', ProductQuestionStatus::Published);
        $useful = $this->question('Useful?', ProductQuestionStatus::Published);
        $useful->setHelpfulCount(6)->save();
        $this->answer($plain, 'waiting', ProductQuestionStatus::Pending);

        $questions = new ProductQuestionRepository();

        self::assertSame(
            ['Useful?', 'Plain?'],
            array_map(static fn (ProductQuestion $question): ?string => $question->getContent(), $questions->findPublishedForProduct((int) $this->product->getId(), 'en_US')),
        );

        $page = $questions->searchForModeration(new ProductQuestionListFilters(productId: (int) $this->product->getId(), pendingAnswers: true));

        self::assertSame(1, $page['total']);
        self::assertSame('Plain?', $page['items'][0]->getContent());
    }

    /**
     * The database holds the rule: a second vote of the same customer is not counted, and the
     * counters of the answer and of its question follow the votes.
     */
    public function testAVoteCountsOncePerCustomerAndMovesBothCounters(): void
    {
        $question = $this->question('Q?');
        $answer = $this->answer($question, 'a', ProductQuestionStatus::Published);
        $other = $this->createFixtureFactory()->customer($this->createFixtureFactory()->customerTitle());

        self::assertTrue($this->answers->addVote((int) $answer->getId(), (int) $this->customer->getId()));
        self::assertFalse($this->answers->addVote((int) $answer->getId(), (int) $this->customer->getId()));
        self::assertTrue($this->answers->addVote((int) $answer->getId(), (int) $other->getId()));

        self::assertSame(2, ProductQuestionAnswerQuery::create()->findPk($answer->getId())?->getHelpfulCount());
        self::assertSame(2, ProductQuestionQuery::create()->findPk($question->getId())?->getHelpfulCount());
        self::assertSame([(int) $answer->getId()], $this->answers->findVotedAnswerIdsByCustomer((int) $this->customer->getId()));
    }

    /**
     * Anonymized, the votes keep counting and nobody can tell whose they were.
     */
    public function testDetachingACustomerKeepsTheirVotesCounting(): void
    {
        $question = $this->question('Q?');
        $answer = $this->answer($question, 'a', ProductQuestionStatus::Published);
        $this->answers->addVote((int) $answer->getId(), (int) $this->customer->getId());

        $this->answers->detachCustomer((int) $this->customer->getId());

        self::assertSame([], $this->answers->findVotedAnswerIdsByCustomer((int) $this->customer->getId()));
        self::assertNull(ProductQuestionAnswerQuery::create()->findPk($answer->getId())?->getCustomerId());
        self::assertSame(1, ProductQuestionAnswerQuery::create()->findPk($answer->getId())?->getHelpfulCount());
    }

    public function testDeletingAQuestionTakesItsAnswers(): void
    {
        $question = $this->question('Q?');
        $answer = $this->answer($question, 'a', ProductQuestionStatus::Published);

        (new ProductQuestionRepository())->delete($question);

        self::assertNull(ProductQuestionAnswerQuery::create()->findPk($answer->getId()));
    }

    private function question(string $content, ProductQuestionStatus $status = ProductQuestionStatus::Published): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setProductId((int) $this->product->getId())
            ->setCustomerId((int) $this->customer->getId())
            ->setLocale('en_US')
            ->setContent($content)
            ->setStatusEnum($status)
            ->save();

        return $question;
    }

    private function answer(ProductQuestion $question, string $content, ProductQuestionStatus $status, bool $official = false, int $helpful = 0): ProductQuestionAnswer
    {
        $answer = new ProductQuestionAnswer();
        $answer
            ->setQuestionId((int) $question->getId())
            ->setIsOfficial($official)
            ->setCustomerId($official ? null : (int) $this->customer->getId())
            ->setContent($content)
            ->setHelpfulCount($helpful)
            ->setStatusEnum($status)
            ->save();

        return $answer;
    }
}
