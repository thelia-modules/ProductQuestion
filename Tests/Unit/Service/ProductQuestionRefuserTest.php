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
use ProductQuestion\Event\ProductQuestionRefusedEvent;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\ProductQuestionRefuser;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ProductQuestionRefuserTest extends TestCase
{
    private InMemoryProductQuestionStorage $storage;

    private EventDispatcher $dispatcher;

    private ProductQuestionRefuser $refuser;

    protected function setUp(): void
    {
        $this->storage = new InMemoryProductQuestionStorage();
        $this->dispatcher = new EventDispatcher();
        $this->refuser = new ProductQuestionRefuser($this->storage, $this->dispatcher);
    }

    public function testRefusingMovesTheStatusAndSaves(): void
    {
        $question = new ProductQuestion();
        $question->setStatusEnum(ProductQuestionStatus::Pending);

        $this->refuser->refuse($question);

        self::assertSame(ProductQuestionStatus::Refused, $question->getStatusEnum());
        self::assertCount(1, $this->storage->saved);
    }

    /**
     * A refused question is off the product page whatever it holds. What a moderator typed is
     * the trace of the decision, and they may want it back if they change their mind.
     */
    public function testRefusingAnAnsweredQuestionLeavesTheAnswerWhereItIs(): void
    {
        $question = new ProductQuestion();
        $question
            ->setAnswer('Oui, compatible.')
            ->setAnsweredBy(7)
            ->setAnsweredAt(new \DateTimeImmutable('2026-01-15 10:00:00'))
            ->setStatusEnum(ProductQuestionStatus::Answered);

        $this->refuser->refuse($question);

        self::assertSame(ProductQuestionStatus::Refused, $question->getStatusEnum());
        self::assertSame('Oui, compatible.', $question->getAnswer());
        self::assertSame(7, $question->getAnsweredBy());
        self::assertNotNull($question->getAnsweredAt());
    }

    public function testTheRefusedEventCarriesTheQuestion(): void
    {
        $seen = [];
        $this->dispatcher->addListener(
            ProductQuestionRefusedEvent::class,
            static function (ProductQuestionRefusedEvent $event) use (&$seen): void {
                $seen[] = $event->getQuestion();
            }
        );

        $question = new ProductQuestion();
        $this->refuser->refuse($question);

        self::assertSame([$question], $seen);
    }
}
