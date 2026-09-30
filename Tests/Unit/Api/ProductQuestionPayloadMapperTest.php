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
use ProductQuestion\Model\ProductQuestionAnswer;
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
            ->setCreatedAt(new \DateTimeImmutable('2026-01-10 09:00:00', new \DateTimeZone('UTC')))
            ->setStatusEnum($status);

        return $question;
    }

    /**
     * @return list<ProductQuestionAnswer>
     */
    private function answers(): array
    {
        $official = (new ProductQuestionAnswer())
            ->setId(8)
            ->setQuestionId(5)
            ->setIsOfficial(true)
            ->setAdminId(7)
            ->setContent('Oui, compatible.')
            ->setHelpfulCount(3)
            ->setPublishedAt(new \DateTimeImmutable('2026-01-15 10:00:00', new \DateTimeZone('UTC')))
            ->setStatusEnum(ProductQuestionStatus::Published);
        $customer = (new ProductQuestionAnswer())
            ->setId(9)
            ->setQuestionId(5)
            ->setIsOfficial(false)
            ->setCustomerId(43)
            ->setContent('Chez moi aussi.')
            ->setHelpfulCount(1)
            ->setPublishedAt(new \DateTimeImmutable('2026-01-16 10:00:00', new \DateTimeZone('UTC')))
            ->setStatusEnum(ProductQuestionStatus::Published);

        return [$official, $customer];
    }

    public function testAPublishedQuestionCarriesItsAnswersAndTheShopAnswerOnItsOwn(): void
    {
        $resource = (new ProductQuestionPayloadMapper())->toResource($this->question(ProductQuestionStatus::Published), $this->answers());

        self::assertSame(5, $resource->id);
        self::assertSame(12, $resource->productId);
        self::assertSame('fr_FR', $resource->locale);
        self::assertSame('Est-ce compatible ?', $resource->content);
        self::assertSame('2026-01-10T09:00:00+00:00', $resource->createdAt);
        self::assertTrue($resource->published);
        // What a client written against 1.2.0 reads.
        self::assertSame('Oui, compatible.', $resource->answer);
        self::assertSame('2026-01-15T10:00:00+00:00', $resource->answeredAt);
        self::assertSame([
            ['id' => 8, 'content' => 'Oui, compatible.', 'official' => true, 'helpfulCount' => 3, 'publishedAt' => '2026-01-15T10:00:00+00:00'],
            ['id' => 9, 'content' => 'Chez moi aussi.', 'official' => false, 'helpfulCount' => 1, 'publishedAt' => '2026-01-16T10:00:00+00:00'],
        ], $resource->answers);
    }

    /**
     * A question off the page shows none of its answers, even one handed over by mistake.
     */
    public function testAnUnpublishedQuestionShowsNoAnswer(): void
    {
        $mapper = new ProductQuestionPayloadMapper();

        foreach ([ProductQuestionStatus::Pending, ProductQuestionStatus::Refused] as $status) {
            $resource = $mapper->toResource($this->question($status), $this->answers());

            self::assertFalse($resource->published);
            self::assertNull($resource->answer);
            self::assertNull($resource->answeredAt);
            self::assertSame([], $resource->answers);
        }
    }

    public function testAnAnswerThatIsNotPublishedIsLeftOut(): void
    {
        $answers = $this->answers();
        $answers[1]->setStatusEnum(ProductQuestionStatus::Pending);

        $resource = (new ProductQuestionPayloadMapper())->toResource($this->question(ProductQuestionStatus::Published), $answers);

        self::assertSame([8], array_column($resource->answers, 'id'));
    }

    public function testNeitherTheCustomersNorTheAdministratorAreInThePayload(): void
    {
        $resource = (new ProductQuestionPayloadMapper())->toResource($this->question(ProductQuestionStatus::Published), $this->answers());

        $properties = array_keys(get_object_vars($resource));

        self::assertNotContains('customerId', $properties);
        self::assertNotContains('answeredBy', $properties);
        self::assertSame(['id', 'content', 'official', 'helpfulCount', 'publishedAt'], array_keys($resource->answers[0]));
        self::assertStringNotContainsString('42', serialize($resource));
        self::assertStringNotContainsString('43', serialize($resource));
    }
}
