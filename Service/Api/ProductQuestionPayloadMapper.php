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

/**
 * A stored question, as the front API hands it over.
 *
 * The row carries more than a visitor may see: who asked, who answered, the moderation
 * status. The resource is built field by field rather than mapped column by column, so a
 * column added to the table later cannot turn up in a public payload by itself.
 */
final readonly class ProductQuestionPayloadMapper
{
    public function toResource(ProductQuestion $question): ProductQuestionResource
    {
        $resource = new ProductQuestionResource();

        $resource->id = $question->getId();
        $resource->productId = $question->getProductId();
        $resource->locale = $question->getLocale();
        $resource->content = $question->getContent();
        $resource->published = $question->isAnswered();
        // A pending question has no answer to show, and a refused one keeps whatever was
        // drafted for the moderator's eyes only.
        $resource->answer = $resource->published ? $question->getAnswer() : null;
        $resource->answeredAt = $resource->published ? self::date($question->getAnsweredAt()) : null;
        $resource->createdAt = self::date($question->getCreatedAt());

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
