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
use ProductQuestion\ProductQuestion;
use ProductQuestion\Service\Notification\ProductQuestionNotification;
use ProductQuestion\Tests\Double\FixedTranslator;

/**
 * What the shop is told about a new question, and in which words.
 */
final class ProductQuestionNotificationTest extends TestCase
{
    public function testTheMailCarriesTheQuestionWhoAskedAndWhichProduct(): void
    {
        $translator = new FixedTranslator();

        $parameters = (new ProductQuestionNotification($translator))->parameters(
            questionId: 5,
            content: 'Est-ce compatible ?',
            questionLocale: 'fr_FR',
            customerName: 'Jane Doe',
            productTitle: 'Horatio',
            adminUrl: 'https://shop.test/admin/module/ProductQuestion/5',
            shopLocale: 'fr_FR',
        );

        self::assertSame([
            'id' => 5,
            'content' => 'Est-ce compatible ?',
            'locale' => 'fr_FR',
            'customerName' => 'Jane Doe',
            'productTitle' => 'Horatio',
            'adminUrl' => 'https://shop.test/admin/module/ProductQuestion/5',
        ], $parameters['question']);

        self::assertSame(['subject', 'heading', 'intro', 'link', 'linkWithUrl', 'outro'], array_keys($parameters['labels']));

        // Every string goes through the module's email catalogue, in the shop's language.
        foreach ($translator->calls as $call) {
            self::assertSame(ProductQuestion::MESSAGE_DOMAIN_EMAIL, $call['domain']);
        }
    }

    /**
     * A deleted account and a product with no title in the shop's language still make a
     * readable mail.
     */
    public function testMissingNamesFallBackToNeutralWords(): void
    {
        $translator = new FixedTranslator();

        $parameters = (new ProductQuestionNotification($translator))->parameters(5, 'Q ?', 'fr_FR', null, null, 'https://shop.test/q/5', 'en_US');

        self::assertSame(ProductQuestion::MESSAGE_DOMAIN_EMAIL.':A customer', $parameters['question']['customerName']);
        self::assertSame(ProductQuestion::MESSAGE_DOMAIN_EMAIL.':Product #%id', $parameters['question']['productTitle']);
        self::assertSame(ProductQuestion::MESSAGE_DOMAIN_EMAIL.':A customer', $parameters['question']['customerName']);
    }
}
