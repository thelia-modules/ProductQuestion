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
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\ProductQuestion;
use ProductQuestion\Service\BackOffice\ProductQuestionStatusCatalog;
use ProductQuestion\Tests\Double\FixedTranslator;

final class ProductQuestionStatusCatalogTest extends TestCase
{
    private FixedTranslator $translator;

    private ProductQuestionStatusCatalog $catalog;

    protected function setUp(): void
    {
        $this->translator = new FixedTranslator();
        $this->catalog = new ProductQuestionStatusCatalog($this->translator);
    }

    public function testEveryStatusIsOfferedKeyedByItsStoredValue(): void
    {
        $all = $this->catalog->all();

        self::assertSame(
            [
                ProductQuestionStatus::Pending->value,
                ProductQuestionStatus::Answered->value,
                ProductQuestionStatus::Refused->value,
            ],
            array_keys($all)
        );
        self::assertSame('success', $all[ProductQuestionStatus::Answered->value]['css']);
    }

    /**
     * An injected translator answers in the core domain by default, where none of these keys
     * exist, and hands back the key itself when it finds nothing. Leaving the domain out
     * therefore looks exactly like a translation that worked.
     */
    public function testEveryLabelIsAskedForInTheModuleBackOfficeDomain(): void
    {
        $this->catalog->all();

        self::assertNotSame([], $this->translator->calls);

        foreach ($this->translator->calls as $call) {
            self::assertSame(ProductQuestion::MESSAGE_DOMAIN_BO, $call['domain']);
        }
    }

    /**
     * A row holding a value no version of this module ever wrote is shown as unknown. Passing
     * it off as pending would put it in a moderator's queue as if it were waiting for them.
     */
    public function testAnUnknownStoredValueIsShownAsUnknown(): void
    {
        $entry = $this->catalog->get(99);

        self::assertSame(99, $entry['value']);
        self::assertStringEndsWith(':Unknown', $entry['label']);
        self::assertSame('light', $entry['css']);
    }

    public function testAKnownStoredValueIsShownAsItself(): void
    {
        $entry = $this->catalog->get(ProductQuestionStatus::Refused->value);

        self::assertSame(ProductQuestionStatus::Refused->value, $entry['value']);
        self::assertSame('danger', $entry['css']);
        self::assertStringEndsWith(':Refused', $entry['label']);
    }
}
