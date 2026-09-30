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

namespace ProductQuestion\Service;

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\Customer;

/**
 * The personal data this module holds about a customer: the questions they asked.
 *
 * Picked up by the core's exporter and anonymizer through the interface, so a customer who
 * asks for their data gets their questions with it, and anonymizing an account detaches its
 * questions the way deleting it already does through the schema. The questions themselves
 * stay: a published answer is the shop's content on the product page, only who asked it goes.
 */
final readonly class ProductQuestionPersonalDataProvider implements CustomerPersonalDataProviderInterface
{
    public const SECTION_NAME = 'product_question';

    public function __construct(
        private ProductQuestionStorageInterface $storage,
    ) {
    }

    public function getPersonalDataSectionName(): string
    {
        return self::SECTION_NAME;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportPersonalData(Customer $customer): array
    {
        return array_map(
            static fn (ProductQuestion $question): array => [
                'id' => $question->getId(),
                'product_id' => $question->getProductId(),
                'locale' => $question->getLocale(),
                'content' => $question->getContent(),
                // Pending questions are exported too: what the customer wrote is theirs
                // whether or not the shop has published it.
                'status' => null === $question->getStatusEnum() ? 'unknown' : strtolower($question->getStatusEnum()->name),
                'answer' => $question->getAnswer(),
                'answered_at' => $question->getAnsweredAt()?->format(\DateTimeInterface::ATOM),
                'created_at' => $question->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ],
            $this->storage->findByCustomer((int) $customer->getId()),
        );
    }

    public function anonymizePersonalData(Customer $customer): void
    {
        foreach ($this->storage->findByCustomer((int) $customer->getId()) as $question) {
            $question->setCustomerId(null);

            $this->storage->save($question);
        }
    }
}
