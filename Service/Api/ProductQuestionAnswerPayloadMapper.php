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

use ProductQuestion\Api\Resource\ProductQuestionAnswer as ProductQuestionAnswerResource;
use ProductQuestion\Model\ProductQuestionAnswer;

/**
 * A stored answer as the front API hands it over, field by field: neither the customer who wrote
 * it nor the administrator is ever in it.
 */
final readonly class ProductQuestionAnswerPayloadMapper
{
    public function toResource(ProductQuestionAnswer $answer): ProductQuestionAnswerResource
    {
        $resource = new ProductQuestionAnswerResource();

        $resource->id = $answer->getId();
        $resource->questionId = $answer->getQuestionId();
        $resource->content = $answer->getContent();
        $resource->official = $answer->isOfficialAnswer();
        $resource->helpfulCount = (int) $answer->getHelpfulCount();
        $resource->published = $answer->isPublished();

        $publishedAt = $answer->getPublishedAt();
        $resource->publishedAt = $resource->published && $publishedAt instanceof \DateTimeInterface
            ? $publishedAt->format(\DateTimeInterface::ATOM)
            : null;

        return $resource;
    }
}
