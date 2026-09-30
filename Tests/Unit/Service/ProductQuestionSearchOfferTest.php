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
use ProductQuestion\Service\Front\ProductQuestionSearchOffer;
use ProductQuestion\Service\Front\QuestionSearchTerm;
use ProductQuestion\Tests\Double\FixedSettings;

final class ProductQuestionSearchOfferTest extends TestCase
{
    public function testNoThresholdNeverOffersASearch(): void
    {
        $offer = new ProductQuestionSearchOffer(new FixedSettings());

        self::assertFalse($offer->isEnabled());
        self::assertFalse($offer->isOfferedFor(10000));
    }

    /**
     * "Above" the threshold: a product with exactly as many questions has no search yet.
     */
    public function testASearchIsOfferedAboveTheThreshold(): void
    {
        $offer = new ProductQuestionSearchOffer(new FixedSettings(searchThreshold: 5));

        self::assertTrue($offer->isEnabled());
        self::assertFalse($offer->isOfferedFor(5));
        self::assertTrue($offer->isOfferedFor(6));
    }

    public function testATypedTermIsCleanedAndTooShortOnesAreNoSearch(): void
    {
        self::assertSame('fold flat', QuestionSearchTerm::normalize("  fold \n  <b>flat</b> "));
        self::assertNull(QuestionSearchTerm::normalize('a'));
        self::assertNull(QuestionSearchTerm::normalize('   '));
        self::assertNull(QuestionSearchTerm::normalize(['fold']));
        self::assertNull(QuestionSearchTerm::normalize(null));
        self::assertSame(QuestionSearchTerm::MAXIMUM_LENGTH, mb_strlen((string) QuestionSearchTerm::normalize(str_repeat('é', 500))));
    }

    /**
     * A visitor's % or _ is a character to find, not a wildcard that matches every question.
     */
    public function testTheLikePatternEscapesTheWildcards(): void
    {
        self::assertSame('%100\\% cotton\\_x\\\\%', QuestionSearchTerm::likePattern('100% cotton_x\\'));
    }
}
