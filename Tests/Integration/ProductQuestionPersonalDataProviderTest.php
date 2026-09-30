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
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\ProductQuestionPersonalDataProvider;
use ProductQuestion\Tests\Double\InMemoryProductQuestionAnswerStorage;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\Customer;
use Thelia\Test\IntegrationTestCase;

/**
 * What the module hands to the core when a customer's data is exported or anonymized: the
 * questions they asked, the answers they wrote and the answers they voted for, whatever their
 * status, and nothing about anyone else.
 *
 * Here rather than in the unit suite: the provider takes a Thelia\Model\Customer, whose Propel
 * base only exists in a booted install. The storage is still the in-memory one.
 */
final class ProductQuestionPersonalDataProviderTest extends IntegrationTestCase
{
    private InMemoryProductQuestionStorage $storage;

    private InMemoryProductQuestionAnswerStorage $answers;

    private ProductQuestionPersonalDataProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = new InMemoryProductQuestionStorage([
            $this->question(1, 34, ProductQuestionStatus::Published, 'Compatible ?'),
            $this->question(2, 34, ProductQuestionStatus::Pending, 'Livraison ?'),
            $this->question(3, 56, ProductQuestionStatus::Published, 'Autre client ?'),
        ]);
        $this->answers = new InMemoryProductQuestionAnswerStorage([
            (new ProductQuestionAnswer())->setId(10)->setQuestionId(3)->setCustomerId(34)->setContent('Chez moi oui.')->setStatusEnum(ProductQuestionStatus::Pending),
            (new ProductQuestionAnswer())->setId(11)->setQuestionId(1)->setIsOfficial(true)->setAdminId(7)->setContent('Oui.')->setStatusEnum(ProductQuestionStatus::Published),
            (new ProductQuestionAnswer())->setId(12)->setQuestionId(1)->setCustomerId(56)->setContent('Pareil.')->setStatusEnum(ProductQuestionStatus::Published),
        ]);
        $this->answers->addVote(12, 34);
        $this->answers->addVote(11, 56);
        $this->provider = new ProductQuestionPersonalDataProvider($this->storage, $this->answers);
    }

    private function question(int $id, int $customerId, ProductQuestionStatus $status, string $content): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setId($id)
            ->setProductId(12)
            ->setCustomerId($customerId)
            ->setLocale('fr_FR')
            ->setContent($content)
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
     * Pending ones included: what the customer wrote is their data whether or not the shop has
     * published it.
     */
    public function testTheExportCarriesTheCustomersQuestionsAnswersAndVotesWhateverTheirStatus(): void
    {
        $export = $this->provider->exportPersonalData($this->customer(34));

        self::assertSame([1, 2], array_column($export['questions'], 'id'));
        self::assertSame('Compatible ?', $export['questions'][0]['content']);
        self::assertSame('published', $export['questions'][0]['status']);
        self::assertSame('pending', $export['questions'][1]['status']);
        self::assertSame(12, $export['questions'][0]['product_id']);
        self::assertSame('fr_FR', $export['questions'][0]['locale']);
        self::assertSame([10], array_column($export['answers'], 'id'));
        self::assertSame('Chez moi oui.', $export['answers'][0]['content']);
        self::assertSame([12], $export['helpful_votes']);
    }

    public function testACustomerWithNothingHasEmptyLists(): void
    {
        self::assertSame(['questions' => [], 'answers' => [], 'helpful_votes' => []], $this->provider->exportPersonalData($this->customer(99)));
    }

    /**
     * Anonymizing detaches the questions, answers and votes from the account, as deleting the
     * account already does through the schema: the texts stay, the votes keep counting, who
     * wrote or cast them is gone. Someone else's are not touched.
     */
    public function testAnonymizingDetachesTheCustomersContributionsAndKeepsThem(): void
    {
        $this->provider->anonymizePersonalData($this->customer(34));

        self::assertNull($this->storage->findById(1)?->getCustomerId());
        self::assertNull($this->storage->findById(2)?->getCustomerId());
        self::assertSame(56, $this->storage->findById(3)?->getCustomerId());
        self::assertSame('Compatible ?', $this->storage->findById(1)?->getContent());
        self::assertNull($this->answers->findById(10)?->getCustomerId());
        self::assertSame('Chez moi oui.', $this->answers->findById(10)?->getContent());
        self::assertSame(56, $this->answers->findById(12)?->getCustomerId());
        self::assertSame([], $this->answers->findVotedAnswerIdsByCustomer(34));
        self::assertSame([11], $this->answers->findVotedAnswerIdsByCustomer(56));
        self::assertSame(1, $this->answers->findById(12)?->getHelpfulCount());
        self::assertSame([], $this->storage->deleted);
        self::assertSame([], $this->answers->deleted);
    }
}
