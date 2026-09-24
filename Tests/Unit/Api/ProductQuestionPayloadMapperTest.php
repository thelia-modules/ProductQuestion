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

namespace ProductQuestion\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\Api\ProductQuestionPayloadMapper;

final class ProductQuestionPayloadMapperTest extends TestCase
{
    private function question(ProductQuestionStatus $status): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setId(5)
            ->setProductId(12)
            ->setCustomerId(42)
            ->setLocale('fr_FR')
            ->setContent('Est-ce compatible ?')
            ->setAnswer('Brouillon ou reponse.')
            ->setAnsweredBy(7)
            ->setAnsweredAt(new \DateTimeImmutable('2026-01-15 10:00:00', new \DateTimeZone('UTC')))
            ->setCreatedAt(new \DateTimeImmutable('2026-01-10 09:00:00', new \DateTimeZone('UTC')))
            ->setStatusEnum($status);

        return $question;
    }

    public function testAnAnsweredQuestionCarriesItsAnswerAndDate(): void
    {
        $resource = (new ProductQuestionPayloadMapper())->toResource($this->question(ProductQuestionStatus::Answered));

        self::assertSame(5, $resource->id);
        self::assertSame(12, $resource->productId);
        self::assertSame('fr_FR', $resource->locale);
        self::assertSame('Est-ce compatible ?', $resource->content);
        self::assertSame('Brouillon ou reponse.', $resource->answer);
        self::assertSame('2026-01-15T10:00:00+00:00', $resource->answeredAt);
        self::assertSame('2026-01-10T09:00:00+00:00', $resource->createdAt);
        self::assertTrue($resource->published);
    }

    /**
     * A refused question keeps what a moderator drafted; the poster reading their own
     * question back must not see it.
     */
    public function testAnUnansweredQuestionHidesTheDraftedAnswer(): void
    {
        $mapper = new ProductQuestionPayloadMapper();

        foreach ([ProductQuestionStatus::Pending, ProductQuestionStatus::Refused] as $status) {
            $resource = $mapper->toResource($this->question($status));

            self::assertFalse($resource->published);
            self::assertNull($resource->answer);
            self::assertNull($resource->answeredAt);
        }
    }

    public function testNeitherTheCustomerNorTheAdministratorIsInThePayload(): void
    {
        $resource = (new ProductQuestionPayloadMapper())->toResource($this->question(ProductQuestionStatus::Answered));

        $properties = array_keys(get_object_vars($resource));

        self::assertNotContains('customerId', $properties);
        self::assertNotContains('answeredBy', $properties);
        self::assertStringNotContainsString('42', serialize($resource));
    }
}
