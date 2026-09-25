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
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\BackOffice\ProductQuestionEditPresenter;
use ProductQuestion\Service\BackOffice\ProductQuestionStatusCatalog;
use ProductQuestion\Tests\Double\FixedShopContext;
use ProductQuestion\Tests\Double\FixedTranslator;
use ProductQuestion\Tests\Double\InMemoryProductTitles;

final class ProductQuestionEditPresenterTest extends TestCase
{
    private function presenter(): ProductQuestionEditPresenter
    {
        return new ProductQuestionEditPresenter(
            new ProductQuestionStatusCatalog(new FixedTranslator()),
            new InMemoryProductTitles(['fr_FR' => [12 => 'Horatio'], 'en_US' => [12 => 'Horatio EN']]),
            new FixedShopContext(),
        );
    }

    private function question(?int $customerId = 34): ProductQuestion
    {
        return (new ProductQuestion())
            ->setId(5)
            ->setProductId(12)
            ->setCustomerId($customerId)
            ->setLocale('en_US')
            ->setContent('Est-ce compatible ?')
            ->setStatusEnum(ProductQuestionStatus::Answered);
    }

    public function testTheScreenLinksToTheCustomerAndTheProductOfTheQuestion(): void
    {
        $question = $this->question();

        $view = $this->presenter()->present($question, 'fr_FR');

        self::assertSame($question, $view['question']);
        self::assertSame(ProductQuestionStatus::Answered->value, $view['questionStatus']['value']);
        self::assertSame('success', $view['questionStatus']['css']);
        self::assertSame('https://shop.test/admin/customer/update?customer_id=34', $view['customerUrl']);
        self::assertSame('https://shop.test/admin/products/update?product_id=12', $view['productUrl']);
        // The moderator's language, not the one the question was asked in.
        self::assertSame('Horatio', $view['productTitle']);
    }

    /**
     * The status variable must never be named `status`: the debug toolbar reads a Twig global of
     * that name, and TwigParser makes every template variable one.
     */
    public function testNoVariableShadowsTheProfilerGlobals(): void
    {
        $view = $this->presenter()->present($this->question(), 'fr_FR');

        foreach (['status', 'token', 'name', 'link', 'collector', 'profile'] as $reserved) {
            self::assertArrayNotHasKey($reserved, $view);
        }
    }

    public function testAClosedAccountHasNoLink(): void
    {
        self::assertNull($this->presenter()->present($this->question(null), 'fr_FR')['customerUrl']);
    }
}
