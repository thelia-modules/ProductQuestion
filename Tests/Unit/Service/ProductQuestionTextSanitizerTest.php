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
use ProductQuestion\Service\Front\ProductQuestionTextSanitizer;

final class ProductQuestionTextSanitizerTest extends TestCase
{
    private ProductQuestionTextSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new ProductQuestionTextSanitizer();
    }

    public function testMarkupIsRemoved(): void
    {
        self::assertSame(
            'Does it fit a 50 cm shelf?',
            $this->sanitizer->sanitize('Does it fit a <b>50 cm</b> shelf?')
        );
    }

    /**
     * The reason this does not use strip_tags(): a question about dimensions reads like an
     * unclosed tag, and strip_tags eats everything up to the next closing bracket.
     */
    public function testComparisonSignsSurviveIntact(): void
    {
        $asked = 'Is it < 6 kg and > 3 kg?';

        self::assertSame($asked, $this->sanitizer->sanitize($asked));
    }

    /**
     * A tag written as entities is a tag as soon as something decodes it once, and doubly
     * encoded twice. The cleaning repeats until the text stops changing.
     */
    public function testEncodedMarkupIsRemovedHoweverDeepItIsEncoded(): void
    {
        self::assertSame('alert(1)', $this->sanitizer->sanitize('&lt;script&gt;alert(1)&lt;/script&gt;'));
        self::assertSame('alert(1)', $this->sanitizer->sanitize('&amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt;'));
    }

    public function testInvisibleCharactersAreRemoved(): void
    {
        self::assertSame('bonjour', $this->sanitizer->sanitize("bon\u{200B}jo\u{FEFF}ur"));
    }

    /**
     * An answer of several paragraphs is normal. What is not is a wall of blank lines, or
     * trailing spaces that push the text off its line.
     */
    public function testParagraphBreaksSurviveAndBlankLineRunsAreCollapsed(): void
    {
        self::assertSame(
            "First paragraph.\n\nSecond paragraph.",
            $this->sanitizer->sanitize("  First    paragraph.  \n\n\n\n   Second paragraph.   ")
        );
    }

    public function testNullIsAnEmptyString(): void
    {
        self::assertSame('', $this->sanitizer->sanitize(null));
        self::assertSame('', $this->sanitizer->sanitize('   '));
    }
}
