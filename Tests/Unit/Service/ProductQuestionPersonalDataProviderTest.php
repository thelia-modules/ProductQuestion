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
use ProductQuestion\Service\ProductQuestionPersonalDataProvider;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\Customer;

/**
 * What the module hands to the core when a customer's data is exported or anonymized: the
 * questions they asked, whatever their status, and nothing about anyone else.
 */
final class ProductQuestionPersonalDataProviderTest extends TestCase
{
    private InMemoryProductQuestionStorage $storage;

    private ProductQuestionPersonalDataProvider $provider;

    protected function setUp(): void
    {
        $this->storage = new InMemoryProductQuestionStorage([
            $this->question(1, 34, ProductQuestionStatus::Answered, 'Compatible ?', 'Oui.'),
            $this->question(2, 34, ProductQuestionStatus::Pending, 'Livraison ?'),
            $this->question(3, 56, ProductQuestionStatus::Answered, 'Autre client ?', 'Oui.'),
        ]);
        $this->provider = new ProductQuestionPersonalDataProvider($this->storage);
    }

    private function question(int $id, int $customerId, ProductQuestionStatus $status, string $content, ?string $answer = null): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setId($id)
            ->setProductId(12)
            ->setCustomerId($customerId)
            ->setLocale('fr_FR')
            ->setContent($content)
            ->setAnswer($answer)
            ->setStatusEnum($status);

        return $question;
    }

    private function customer(int $id): Customer
    {
        $customer = new Customer();
        $customer->setId($id);

        return $customer;
    }

    public function testItIsACorePersonalDataProviderNamedAfterTheModule(): void
    {
        self::assertInstanceOf(CustomerPersonalDataProviderInterface::class, $this->provider);
        self::assertSame('product_question', $this->provider->getPersonalDataSectionName());
    }

    /**
     * Every question the customer asked, pending ones included: what they wrote is their
     * data whether or not the shop has published it.
     */
    public function testTheExportCarriesTheCustomersQuestionsAndAnswersWhateverTheirStatus(): void
    {
        $export = $this->provider->exportPersonalData($this->customer(34));

        self::assertSame([1, 2], array_column($export, 'id'));
        self::assertSame('Compatible ?', $export[0]['content']);
        self::assertSame('Oui.', $export[0]['answer']);
        self::assertSame('answered', $export[0]['status']);
        self::assertSame('pending', $export[1]['status']);
        self::assertNull($export[1]['answer']);
        self::assertSame(12, $export[0]['product_id']);
        self::assertSame('fr_FR', $export[0]['locale']);
    }

    public function testACustomerWithNoQuestionHasAnEmptySection(): void
    {
        self::assertSame([], $this->provider->exportPersonalData($this->customer(99)));
    }

    /**
     * Anonymizing detaches the questions from the account, as deleting the account already
     * does through the schema: the published question stays on the product page, who asked
     * it is gone. Someone else's questions are not touched.
     */
    public function testAnonymizingDetachesTheCustomersQuestionsAndKeepsThem(): void
    {
        $this->provider->anonymizePersonalData($this->customer(34));

        self::assertNull($this->storage->findById(1)?->getCustomerId());
        self::assertNull($this->storage->findById(2)?->getCustomerId());
        self::assertSame(56, $this->storage->findById(3)?->getCustomerId());
        self::assertSame('Compatible ?', $this->storage->findById(1)?->getContent());
        self::assertCount(2, $this->storage->saved);
        self::assertSame([], $this->storage->deleted);
    }
}
