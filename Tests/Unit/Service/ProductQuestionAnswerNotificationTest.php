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
use ProductQuestion\Service\Notification\ProductQuestionAnswerNotification;
use ProductQuestion\Tests\Double\FixedTranslator;

final class ProductQuestionAnswerNotificationTest extends TestCase
{
    public function testTheMailCarriesTheQuestionTheAnswerAndTheProductPage(): void
    {
        $translator = new FixedTranslator();

        $parameters = (new ProductQuestionAnswerNotification($translator))->parameters(5, 'Est-ce compatible ?', 'Oui, compatible.', 'Horatio', 'https://shop.test/horatio-1.html', 'fr_FR');

        self::assertSame([
            'id' => 5,
            'content' => 'Est-ce compatible ?',
            'answer' => 'Oui, compatible.',
            'productTitle' => 'Horatio',
            'productUrl' => 'https://shop.test/horatio-1.html',
        ], $parameters['question']);
        self::assertSame(['subject', 'heading', 'intro', 'answer', 'link', 'linkWithUrl', 'outro'], array_keys($parameters['labels']));

        // Every string in the module's email catalogue, in the language of the question.
        foreach ($translator->calls as $call) {
            self::assertSame(ProductQuestion::MESSAGE_DOMAIN_EMAIL, $call['domain']);
        }
    }

    public function testAProductWithNoTitleIsStillNamed(): void
    {
        $parameters = (new ProductQuestionAnswerNotification(new FixedTranslator()))->parameters(5, 'Q ?', 'R.', null, 'https://shop.test/p', 'en_US');

        self::assertSame(ProductQuestion::MESSAGE_DOMAIN_EMAIL.':the product', $parameters['question']['productTitle']);
    }
}
