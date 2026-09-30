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
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Repository\ProductQuestionAnswerStorageInterface;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\Customer;

/**
 * The personal data this module holds about a customer: the questions they asked, the answers
 * they wrote and the answers they found helpful.
 *
 * Picked up by the core's exporter and anonymizer through the interface, so a customer who asks
 * for their data gets all three, and anonymizing an account detaches them the way deleting it
 * already does through the schema. The texts themselves stay: a published question or answer is
 * content of the product page, only who wrote it goes; a vote keeps counting, nobody can tell
 * whose it was.
 */
final readonly class ProductQuestionPersonalDataProvider implements CustomerPersonalDataProviderInterface
{
    public const SECTION_NAME = 'product_question';

    public function __construct(
        private ProductQuestionStorageInterface $storage,
        private ProductQuestionAnswerStorageInterface $answers,
    ) {
    }

    public function getPersonalDataSectionName(): string
    {
        return self::SECTION_NAME;
    }

    /**
     * @return array{questions: list<array<string, mixed>>, answers: list<array<string, mixed>>, helpful_votes: list<int>}
     */
    public function exportPersonalData(Customer $customer): array
    {
        $customerId = (int) $customer->getId();

        return [
            // Pending ones too: what the customer wrote is theirs whether or not the shop has
            // published it.
            'questions' => array_map(
                static fn (ProductQuestion $question): array => [
                    'id' => $question->getId(),
                    'product_id' => $question->getProductId(),
                    'locale' => $question->getLocale(),
                    'content' => $question->getContent(),
                    'status' => self::status($question->getStatusEnum()?->name),
                    'notify_author' => $question->getNotifyAuthor(),
                    'created_at' => self::date($question->getCreatedAt()),
                ],
                $this->storage->findByCustomer($customerId),
            ),
            'answers' => array_map(
                static fn (ProductQuestionAnswer $answer): array => [
                    'id' => $answer->getId(),
                    'question_id' => $answer->getQuestionId(),
                    'content' => $answer->getContent(),
                    'status' => self::status($answer->getStatusEnum()?->name),
                    'created_at' => self::date($answer->getCreatedAt()),
                ],
                $this->answers->findByCustomer($customerId),
            ),
            'helpful_votes' => $this->answers->findVotedAnswerIdsByCustomer($customerId),
        ];
    }

    public function anonymizePersonalData(Customer $customer): void
    {
        $customerId = (int) $customer->getId();

        foreach ($this->storage->findByCustomer($customerId) as $question) {
            $question->setCustomerId(null);

            $this->storage->save($question);
        }

        $this->answers->detachCustomer($customerId);
    }

    private static function status(?string $name): string
    {
        return null === $name ? 'unknown' : strtolower($name);
    }

    private static function date(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::ATOM) : null;
    }
}
