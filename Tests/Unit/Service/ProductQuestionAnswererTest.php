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
use ProductQuestion\Exception\InvalidProductQuestionException;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\Front\ProductQuestionTextSanitizer;
use ProductQuestion\Service\ProductQuestionAnswerer;
use ProductQuestion\Tests\Double\InMemoryProductQuestionAnswerStorage;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ProductQuestionAnswererTest extends TestCase
{
    private InMemoryProductQuestionStorage $storage;

    private InMemoryProductQuestionAnswerStorage $answers;

    private EventDispatcher $dispatcher;

    private ProductQuestionAnswerer $answerer;

    protected function setUp(): void
    {
        $this->storage = new InMemoryProductQuestionStorage();
        $this->answers = new InMemoryProductQuestionAnswerStorage();
        $this->dispatcher = new EventDispatcher();
        $this->answerer = new ProductQuestionAnswerer(
            $this->storage,
            $this->answers,
            new ProductQuestionTextSanitizer(),
            $this->dispatcher,
        );
    }

    private function pendingQuestion(): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setProductId(12)
            ->setCustomerId(34)
            ->setLocale('fr_FR')
            ->setContent('Est-ce compatible ?')
            ->setStatusEnum(ProductQuestionStatus::Pending);
        $this->storage->save($question);
        $this->storage->saved = [];

        return $question;
    }

    private function official(ProductQuestion $question): ?\ProductQuestion\Model\ProductQuestionAnswer
    {
        return $this->answers->findOfficialForQuestion((int) $question->getId());
    }

    /**
     * The shop's answer is an answer row of its own, official, published as it is written, and
     * writing it publishes the question: a question hidden under a published answer, or an
     * answer without its date, would be a page that lies.
     */
    public function testAnsweringWritesThePublishedOfficialAnswerAndPublishesTheQuestion(): void
    {
        $question = $this->pendingQuestion();

        $answer = $this->answerer->answer($question, 'Oui, il est compatible.', 7);

        self::assertSame($answer, $this->official($question));
        self::assertSame('Oui, il est compatible.', $answer->getContent());
        self::assertTrue($answer->isOfficialAnswer());
        self::assertSame(7, $answer->getAdminId());
        self::assertNull($answer->getCustomerId());
        self::assertSame($question->getId(), $answer->getQuestionId());
        self::assertInstanceOf(\DateTimeInterface::class, $answer->getPublishedAt());
        self::assertSame(ProductQuestionStatus::Published, $answer->getStatusEnum());
        self::assertSame(ProductQuestionStatus::Published, $question->getStatusEnum());
        self::assertCount(1, $this->storage->saved);
        self::assertSame([(int) $question->getId()], $this->answers->refreshedQuestions);
    }

    /**
     * One official answer per question: writing it again rewrites it, and keeps the date it was
     * first published.
     */
    public function testAnsweringAgainRewritesTheSameOfficialAnswer(): void
    {
        $question = $this->pendingQuestion();
        $first = $this->answerer->answer($question, 'Premiere reponse.', 7);
        $publishedAt = $first->getPublishedAt();

        $second = $this->answerer->answer($question, 'Reponse corrigee.', 9);

        self::assertSame($first, $second);
        self::assertCount(1, $this->answers->findForQuestion((int) $question->getId()));
        self::assertSame($publishedAt, $second->getPublishedAt());
    }

    public function testTheStoredAnswerIsTheCleanedText(): void
    {
        $question = $this->pendingQuestion();

        $answer = $this->answerer->answer($question, '  Oui, <b>compatible</b>.  ', 7);

        self::assertSame('Oui, compatible.', $answer->getContent());
    }

    public function testTheAnsweredEventCarriesTheQuestionAndTheAnswer(): void
    {
        $seen = [];
        $this->dispatcher->addListener(
            ProductQuestionAnsweredEvent::class,
            static function (ProductQuestionAnsweredEvent $event) use (&$seen): void {
                $seen[] = [$event->getQuestion(), $event->getAnswer()];
            }
        );

        $question = $this->pendingQuestion();
        $answer = $this->answerer->answer($question, 'Oui, compatible.', 7);

        self::assertSame([[$question, $answer]], $seen);
    }

    /**
     * The customer is told once: the event says whether this answer puts the question on the
     * page or rewrites what is already there.
     */
    public function testTheEventTellsAFirstAnswerFromAnEdit(): void
    {
        $flags = [];
        $this->dispatcher->addListener(
            ProductQuestionAnsweredEvent::class,
            static function (ProductQuestionAnsweredEvent $event) use (&$flags): void {
                $flags[] = $event->isFirstAnswer();
            }
        );

        $question = $this->pendingQuestion();
        $this->answerer->answer($question, 'Oui, compatible.', 7);
        $this->answerer->answer($question, 'Oui, tout a fait compatible.', 7);

        self::assertSame([true, false], $flags);
    }

    /**
     * A refusal keeps the answer. A question published, refused, then answered again has
     * already been announced to its customer: the second publication is an edit, not a first
     * answer, whatever the status said in between.
     */
    public function testAnAnswerRepublishedAfterARefusalIsNotAFirstAnswer(): void
    {
        $flags = [];
        $this->dispatcher->addListener(
            ProductQuestionAnsweredEvent::class,
            static function (ProductQuestionAnsweredEvent $event) use (&$flags): void {
                $flags[] = $event->isFirstAnswer();
            }
        );

        $question = $this->pendingQuestion();
        $this->answerer->answer($question, 'Oui, compatible.', 7);
        $question->setStatusEnum(ProductQuestionStatus::Refused);
        $this->answerer->answer($question, 'Oui, compatible.', 7);

        self::assertSame([true, false], $flags);
    }

    /**
     * A question refused before anyone wrote an answer is still unanswered: publishing it
     * afterwards is the first answer, and the customer hears about it.
     */
    public function testAnsweringAQuestionRefusedWithoutAnAnswerIsAFirstAnswer(): void
    {
        $flags = [];
        $this->dispatcher->addListener(
            ProductQuestionAnsweredEvent::class,
            static function (ProductQuestionAnsweredEvent $event) use (&$flags): void {
                $flags[] = $event->isFirstAnswer();
            }
        );

        $question = $this->pendingQuestion();
        $question->setStatusEnum(ProductQuestionStatus::Refused);
        $this->answerer->answer($question, 'Oui, compatible.', 7);

        self::assertSame([true], $flags);
    }

    /**
     * A moderator who saves an empty textarea has not answered. Publishing that would put a
     * heading with nothing under it on the product page.
     */
    public function testAnEmptyAnswerIsRefusedAndLeavesTheQuestionPending(): void
    {
        $question = $this->pendingQuestion();

        try {
            $this->answerer->answer($question, '   ', 7);
            self::fail('An empty answer must be refused.');
        } catch (InvalidProductQuestionException) {
            // Expected.
        }

        self::assertSame(ProductQuestionStatus::Pending, $question->getStatusEnum());
        self::assertNull($this->official($question));
        self::assertSame([], $this->storage->saved);
        self::assertSame([], $this->answers->saved);
    }

    public function testAnAnswerThatCleansDownToNothingIsRefused(): void
    {
        $this->expectException(InvalidProductQuestionException::class);

        $this->answerer->answer($this->pendingQuestion(), '<p></p>', 7);
    }

    public function testATooLongAnswerIsRefused(): void
    {
        $this->expectException(InvalidProductQuestionException::class);

        $this->answerer->answer(
            $this->pendingQuestion(),
            str_repeat('a', ProductQuestionAnswerer::MAXIMUM_LENGTH + 1),
            7
        );
    }

    /**
     * The column is nullable because the administrator account may be deleted later. An
     * answer written with no identified author is stored the same way, rather than with a
     * zero pointing at nobody.
     */
    public function testAnUnidentifiedAuthorIsStoredAsNoAuthorRatherThanAsZero(): void
    {
        $question = $this->pendingQuestion();

        $answer = $this->answerer->answer($question, 'Oui, compatible.', 0);

        self::assertNull($answer->getAdminId());
        self::assertSame(ProductQuestionStatus::Published, $question->getStatusEnum());
    }

    /**
     * Editing a published answer goes through the same door and keeps the question answered.
     */
    public function testEditingAPublishedAnswerKeepsItPublished(): void
    {
        $question = $this->pendingQuestion();
        $this->answerer->answer($question, 'Premiere reponse.', 7);

        $answer = $this->answerer->answer($question, 'Reponse corrigee.', 9);

        self::assertSame('Reponse corrigee.', $answer->getContent());
        self::assertSame(9, $answer->getAdminId());
        self::assertSame(ProductQuestionStatus::Published, $question->getStatusEnum());
    }
}
