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
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ProductQuestionAnswererTest extends TestCase
{
    private InMemoryProductQuestionStorage $storage;

    private EventDispatcher $dispatcher;

    private ProductQuestionAnswerer $answerer;

    protected function setUp(): void
    {
        $this->storage = new InMemoryProductQuestionStorage();
        $this->dispatcher = new EventDispatcher();
        $this->answerer = new ProductQuestionAnswerer(
            $this->storage,
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

        return $question;
    }

    /**
     * Answering is what puts a question on the product page, so the four columns that say so
     * move together. A status written without the answer, or an answer without the status,
     * is a question shown with nothing under it or one hidden with everything.
     */
    public function testAnsweringWritesTheAnswerItsDateItsAuthorAndTheStatusTogether(): void
    {
        $question = $this->pendingQuestion();

        $this->answerer->answer($question, 'Oui, il est compatible.', 7);

        self::assertSame('Oui, il est compatible.', $question->getAnswer());
        self::assertSame(7, $question->getAnsweredBy());
        self::assertInstanceOf(\DateTimeInterface::class, $question->getAnsweredAt());
        self::assertSame(ProductQuestionStatus::Answered, $question->getStatusEnum());
        self::assertCount(1, $this->storage->saved);
    }

    public function testTheStoredAnswerIsTheCleanedText(): void
    {
        $question = $this->pendingQuestion();

        $this->answerer->answer($question, '  Oui, <b>compatible</b>.  ', 7);

        self::assertSame('Oui, compatible.', $question->getAnswer());
    }

    public function testTheAnsweredEventCarriesTheQuestion(): void
    {
        $seen = [];
        $this->dispatcher->addListener(
            ProductQuestionAnsweredEvent::class,
            static function (ProductQuestionAnsweredEvent $event) use (&$seen): void {
                $seen[] = $event->getQuestion();
            }
        );

        $question = $this->pendingQuestion();
        $this->answerer->answer($question, 'Oui, compatible.', 7);

        self::assertSame([$question], $seen);
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
        self::assertNull($question->getAnswer());
        self::assertSame([], $this->storage->saved);
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

        $this->answerer->answer($question, 'Oui, compatible.', 0);

        self::assertNull($question->getAnsweredBy());
        self::assertSame(ProductQuestionStatus::Answered, $question->getStatusEnum());
    }

    /**
     * Editing a published answer goes through the same door and keeps the question answered.
     */
    public function testEditingAPublishedAnswerKeepsItPublished(): void
    {
        $question = $this->pendingQuestion();
        $this->answerer->answer($question, 'Premiere reponse.', 7);

        $this->answerer->answer($question, 'Reponse corrigee.', 9);

        self::assertSame('Reponse corrigee.', $question->getAnswer());
        self::assertSame(9, $question->getAnsweredBy());
        self::assertSame(ProductQuestionStatus::Answered, $question->getStatusEnum());
    }
}
