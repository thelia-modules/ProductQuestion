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

namespace ProductQuestion\Tests\Integration;

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionQuery;
use ProductQuestion\Model\ProductQuestionStatus;
use Thelia\Domain\Customer\Service\CustomerAnonymizer;
use Thelia\Domain\Customer\Service\CustomerPersonalDataExporter;
use Thelia\Model\Customer;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;

/**
 * The core's personal data tools, as a shop runs them: the export of a customer carries the
 * questions they asked, and anonymizing the account cuts the link while the questions stay.
 *
 * Runs against the test database with the module active, through the root configuration:
 * vendor/bin/phpunit vendor/thelia/modules/ProductQuestion/Tests/Integration
 */
final class CustomerPersonalDataTest extends IntegrationTestCase
{
    public function testTheExportOfACustomerCarriesTheQuestionsTheyAsked(): void
    {
        [$customer, $product] = $this->customerAndProduct();
        $question = $this->question($product, $customer, 'Is the frame made of oak?');

        $export = $this->getService(CustomerPersonalDataExporter::class)->export($customer);

        self::assertArrayHasKey('product_question', $export);
        self::assertSame([$question->getId()], array_column($export['product_question'], 'id'));
        self::assertSame('Is the frame made of oak?', $export['product_question'][0]['content']);
    }

    public function testAnonymizingTheCustomerCutsTheLinkAndKeepsTheQuestion(): void
    {
        [$customer, $product] = $this->customerAndProduct();
        $question = $this->question($product, $customer, 'Does it fold flat?');

        $this->getService(CustomerAnonymizer::class)->anonymize($customer);

        $stored = ProductQuestionQuery::create()->findPk($question->getId());

        self::assertNotNull($stored);
        self::assertNull($stored->getCustomerId());
        self::assertSame('Does it fold flat?', $stored->getContent());
    }

    /**
     * @return array{Customer, Product}
     */
    private function customerAndProduct(): array
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        return [$customer, $product];
    }

    private function question(Product $product, Customer $customer, string $content): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setProductId((int) $product->getId())
            ->setCustomerId((int) $customer->getId())
            ->setLocale('en_US')
            ->setContent($content)
            ->setStatusEnum(ProductQuestionStatus::Pending)
            ->save();

        return $question;
    }
}
