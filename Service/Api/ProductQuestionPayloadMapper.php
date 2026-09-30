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

namespace ProductQuestion\Service\Api;

use ProductQuestion\Api\Resource\ProductQuestion as ProductQuestionResource;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionAnswer;

/**
 * A stored question, as the front API hands it over.
 *
 * The rows carry more than a visitor may see: who asked, who answered, the moderation status.
 * The resource is built field by field rather than mapped column by column, so a column added
 * to a table later cannot turn up in a public payload by itself.
 */
final readonly class ProductQuestionPayloadMapper
{
    /**
     * @param list<ProductQuestionAnswer> $publishedAnswers the question's published answers, in display order
     */
    public function toResource(ProductQuestion $question, array $publishedAnswers = []): ProductQuestionResource
    {
        $resource = new ProductQuestionResource();

        $resource->id = $question->getId();
        $resource->productId = $question->getProductId();
        $resource->locale = $question->getLocale();
        $resource->content = $question->getContent();
        $resource->published = $question->isPublished();
        $resource->createdAt = self::date($question->getCreatedAt());

        // A question off the page shows none of its answers, whatever they are.
        if (!$resource->published) {
            return $resource;
        }

        foreach ($publishedAnswers as $answer) {
            if (!$answer->isPublished()) {
                continue;
            }

            $resource->answers[] = [
                'id' => (int) $answer->getId(),
                'content' => (string) $answer->getContent(),
                'official' => $answer->isOfficialAnswer(),
                'helpfulCount' => (int) $answer->getHelpfulCount(),
                'publishedAt' => self::date($answer->getPublishedAt()),
            ];

            if (null === $resource->answer && $answer->isOfficialAnswer()) {
                $resource->answer = $answer->getContent();
                $resource->answeredAt = self::date($answer->getPublishedAt());
            }
        }

        return $resource;
    }

    private static function date(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return null === $value || '' === $value ? null : (string) $value;
    }
}
