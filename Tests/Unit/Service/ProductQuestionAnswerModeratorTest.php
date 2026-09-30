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
use ProductQuestion\Event\ProductQuestionAnsweredEvent;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\ProductQuestionAnswerModerator;
use ProductQuestion\Service\ProductQuestionPublisher;
use ProductQuestion\Tests\Double\InMemoryProductQuestionAnswerStorage;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ProductQuestionAnswerModeratorTest extends TestCase
{
    private InMemoryProductQuestionStorage $storage;

    private InMemoryProductQuestionAnswerStorage $answers;

    private ProductQuestionAnswerModerator $moderator;

    /** @var list<array{answer: ?ProductQuestionAnswer, first: bool}> */
    private array $announced = [];

    protected function setUp(): void
    {
        $this->storage = new InMemoryProductQuestionStorage([
            (new ProductQuestion())->setId(1)->setProductId(12)->setCustomerId(34)->setLocale('fr_FR')->setContent('Q ?')->setStatusEnum(ProductQuestionStatus::Published),
        ]);
        $this->answers = new InMemoryProductQuestionAnswerStorage();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ProductQuestionAnsweredEvent::class, function (ProductQuestionAnsweredEvent $event): void {
            $this->announced[] = ['answer' => $event->getAnswer(), 'first' => $event->isFirstAnswer()];
        });
        $this->moderator = new ProductQuestionAnswerModerator($this->storage, $this->answers, $dispatcher);
    }

    private function pendingAnswer(): ProductQuestionAnswer
    {
        $answer = (new ProductQuestionAnswer())->setQuestionId(1)->setCustomerId(56)->setContent('Oui.')->setStatusEnum(ProductQuestionStatus::Pending);
        $this->answers->save($answer);

        return $answer;
    }

    public function testPublishingPutsTheAnswerOnThePageAndTellsTheAuthorOnce(): void
    {
        $answer = $this->pendingAnswer();

        $this->moderator->publish($answer);

        self::assertSame(ProductQuestionStatus::Published, $answer->getStatusEnum());
        self::assertInstanceOf(\DateTimeInterface::class, $answer->getPublishedAt());
        self::assertSame([['answer' => $answer, 'first' => true]], $this->announced);
        self::assertSame([1], $this->answers->refreshedQuestions);
    }

    /**
     * Published, refused, published again: the author heard about it the first time.
     */
    public function testAnAnswerPublishedAgainAfterARefusalIsNotAnnouncedAgain(): void
    {
        $answer = $this->pendingAnswer();

        $this->moderator->publish($answer);
        $this->moderator->refuse($answer);
        $this->moderator->publish($answer);

        self::assertSame([true, false], array_column($this->announced, 'first'));
    }

    public function testRefusingTakesTheAnswerOffAndRecountsTheQuestion(): void
    {
        $answer = $this->pendingAnswer();

        $this->moderator->refuse($answer);

        self::assertSame(ProductQuestionStatus::Refused, $answer->getStatusEnum());
        self::assertSame([], $this->announced);
        self::assertSame([1], $this->answers->refreshedQuestions);
    }

    public function testDeletingRemovesTheAnswerAndRecountsTheQuestion(): void
    {
        $answer = $this->pendingAnswer();

        $this->moderator->delete($answer);

        self::assertSame([$answer], $this->answers->deleted);
        self::assertSame([1], $this->answers->refreshedQuestions);
    }

    /**
     * A question can go on the page without an answer, for customers to answer it.
     */
    public function testAQuestionIsPublishedWithoutAnAnswer(): void
    {
        $question = (new ProductQuestion())->setId(2)->setProductId(12)->setLocale('fr_FR')->setContent('Q ?')->setStatusEnum(ProductQuestionStatus::Pending);
        $storage = new InMemoryProductQuestionStorage([$question]);

        (new ProductQuestionPublisher($storage))->publish($question);

        self::assertSame(ProductQuestionStatus::Published, $question->getStatusEnum());
        self::assertSame([$question], $storage->saved);
    }
}
