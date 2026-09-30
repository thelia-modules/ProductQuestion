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
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\BackOffice\ProductQuestionEditPresenter;
use ProductQuestion\Service\BackOffice\ProductQuestionStatusCatalog;
use ProductQuestion\Tests\Double\FixedShopContext;
use ProductQuestion\Tests\Double\FixedTranslator;
use ProductQuestion\Tests\Double\InMemoryProductQuestionAnswerStorage;
use ProductQuestion\Tests\Double\InMemoryProductTitles;

final class ProductQuestionEditPresenterTest extends TestCase
{
    private function presenter(): ProductQuestionEditPresenter
    {
        return new ProductQuestionEditPresenter(
            new ProductQuestionStatusCatalog(new FixedTranslator()),
            new InMemoryProductTitles(['fr_FR' => [12 => 'Horatio'], 'en_US' => [12 => 'Horatio EN']]),
            new FixedShopContext(),
            $this->answers,
        );
    }

    private InMemoryProductQuestionAnswerStorage $answers;

    protected function setUp(): void
    {
        $this->answers = new InMemoryProductQuestionAnswerStorage([
            (new ProductQuestionAnswer())->setId(1)->setQuestionId(5)->setCustomerId(60)->setContent('Client')->setStatusEnum(ProductQuestionStatus::Pending),
            (new ProductQuestionAnswer())->setId(2)->setQuestionId(5)->setIsOfficial(true)->setAdminId(7)->setContent('Boutique')->setHelpfulCount(4)->setStatusEnum(ProductQuestionStatus::Published),
            (new ProductQuestionAnswer())->setId(3)->setQuestionId(6)->setCustomerId(61)->setContent('Autre question')->setStatusEnum(ProductQuestionStatus::Pending),
        ]);
    }

    /**
     * The shop's answer on its own, the customers' answers under it with their status: what a
     * moderator acts on.
     */
    public function testTheShopAnswerAndTheCustomerAnswersOfTheQuestionAreSeparated(): void
    {
        $view = $this->presenter()->present($this->question(), 'fr_FR');

        self::assertSame('Boutique', $view['officialAnswer']['content'] ?? null);
        self::assertSame(4, $view['officialAnswer']['helpfulCount'] ?? null);
        self::assertSame(['Client'], array_column($view['customerAnswers'], 'content'));
        self::assertSame(ProductQuestionStatus::Pending->value, $view['customerAnswers'][0]['status']['value']);
        self::assertSame('https://shop.test/admin/customer/update?customer_id=60', $view['customerAnswers'][0]['customerUrl']);
    }

    private function question(?int $customerId = 34): ProductQuestion
    {
        return (new ProductQuestion())
            ->setId(5)
            ->setProductId(12)
            ->setCustomerId($customerId)
            ->setLocale('en_US')
            ->setContent('Est-ce compatible ?')
            ->setStatusEnum(ProductQuestionStatus::Published);
    }

    public function testTheScreenLinksToTheCustomerAndTheProductOfTheQuestion(): void
    {
        $question = $this->question();

        $view = $this->presenter()->present($question, 'fr_FR');

        self::assertSame($question, $view['question']);
        self::assertSame(ProductQuestionStatus::Published->value, $view['questionStatus']['value']);
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
