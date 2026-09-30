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

namespace ProductQuestion\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;

final class ProductQuestionStatusTest extends TestCase
{
    /**
     * The integers are in the database of every shop that installed the module. Renumbering
     * a case would move rows from one state to another without touching them.
     */
    public function testTheStoredIntegersNeverMove(): void
    {
        self::assertSame(0, ProductQuestionStatus::Pending->value);
        self::assertSame(1, ProductQuestionStatus::Answered->value);
        self::assertSame(2, ProductQuestionStatus::Refused->value);
        self::assertCount(3, ProductQuestionStatus::cases());
    }

    public function testTheStatusGoesThroughTheEnumBothWays(): void
    {
        $question = new ProductQuestion();
        $question->setStatusEnum(ProductQuestionStatus::Refused);

        // The column is a TINYINT: what reaches Propel is the integer, never the enum.
        self::assertSame(2, $question->getStatus());
        self::assertSame(ProductQuestionStatus::Refused, $question->getStatusEnum());
    }

    /**
     * Only an answered question goes on the product page, so this is the one predicate the
     * front office asks for.
     */
    public function testOnlyAnAnsweredQuestionReportsItself(): void
    {
        $question = new ProductQuestion();

        $question->setStatusEnum(ProductQuestionStatus::Answered);
        self::assertTrue($question->isAnswered());

        $question->setStatusEnum(ProductQuestionStatus::Pending);
        self::assertFalse($question->isAnswered());

        $question->setStatusEnum(ProductQuestionStatus::Refused);
        self::assertFalse($question->isAnswered());
    }

    /**
     * A value no version of this module ever wrote is reported as unknown rather than
     * quietly turned into a pending question: a row nobody can explain must not look like
     * one waiting for an answer.
     */
    public function testAnUnknownStoredValueIsNotPassedOffAsPending(): void
    {
        $question = new ProductQuestion();
        $question->setStatus(99);

        self::assertNull($question->getStatusEnum());
        self::assertFalse($question->isAnswered());
    }
}
