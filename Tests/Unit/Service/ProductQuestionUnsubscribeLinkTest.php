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
use ProductQuestion\Service\Notification\ProductQuestionUnsubscribeLink;
use ProductQuestion\Service\Notification\UnsubscribeLinkCheck;
use ProductQuestion\Tests\Double\FixedShopContext;

final class ProductQuestionUnsubscribeLinkTest extends TestCase
{
    private const NOW = 1_790_000_000;

    private ProductQuestionUnsubscribeLink $link;

    protected function setUp(): void
    {
        $this->link = new ProductQuestionUnsubscribeLink('test-secret', new FixedShopContext());
    }

    public function testTheLinkPointsAtTheQuestionWithADateAndASignature(): void
    {
        $url = $this->link->urlFor(5, self::NOW);

        self::assertStringStartsWith('https://shop.test/product-question/5/unsubscribe?expires='.(self::NOW + ProductQuestionUnsubscribeLink::LIFETIME_SECONDS).'&signature=', $url);
    }

    public function testTheLinkTheShopSignedIsValidUntilItsDate(): void
    {
        ['expires' => $expires, 'signature' => $signature] = $this->link->parameters(5, self::NOW);

        self::assertSame(UnsubscribeLinkCheck::Valid, $this->link->check(5, (string) $expires, $signature, self::NOW));
        self::assertSame(UnsubscribeLinkCheck::Valid, $this->link->check(5, (string) $expires, $signature, $expires));
        self::assertSame(UnsubscribeLinkCheck::Expired, $this->link->check(5, (string) $expires, $signature, $expires + 1));
    }

    /**
     * Another question, a later date, a signature changed by one character, or a secret other
     * than the shop's: none of them passes.
     */
    public function testAForgedLinkIsRefused(): void
    {
        ['expires' => $expires, 'signature' => $signature] = $this->link->parameters(5, self::NOW);

        self::assertSame(UnsubscribeLinkCheck::Invalid, $this->link->check(6, (string) $expires, $signature, self::NOW));
        self::assertSame(UnsubscribeLinkCheck::Invalid, $this->link->check(5, (string) ($expires + 86400), $signature, self::NOW));
        self::assertSame(UnsubscribeLinkCheck::Invalid, $this->link->check(5, (string) $expires, substr($signature, 0, -1).('a' === substr($signature, -1) ? 'b' : 'a'), self::NOW));
        self::assertSame(UnsubscribeLinkCheck::Invalid, (new ProductQuestionUnsubscribeLink('other-secret', new FixedShopContext()))->check(5, (string) $expires, $signature, self::NOW));
    }

    /**
     * A forged link that is also past its date is told it is invalid, not expired: only the shop's
     * own links learn about their date.
     */
    public function testAnExpiredForgeryIsInvalidNotExpired(): void
    {
        self::assertSame(UnsubscribeLinkCheck::Invalid, $this->link->check(5, '1', str_repeat('0', 64), self::NOW));
    }

    public function testMalformedValuesAreRefused(): void
    {
        foreach ([[5, null, 'x'], [5, 'abc', 'x'], [5, ['1'], 'x'], [5, '123', null], [0, '123', 'x']] as [$id, $expires, $signature]) {
            self::assertSame(UnsubscribeLinkCheck::Invalid, $this->link->check($id, $expires, $signature, self::NOW));
        }
    }
}
