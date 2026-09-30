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

namespace ProductQuestion\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ProductQuestion\Api\Resource\ProductQuestionAnswer as ProductQuestionAnswerResource;
use ProductQuestion\Repository\ProductQuestionAnswerStorageInterface;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Repository\ProductVisibilityInterface;
use ProductQuestion\Service\Api\ProductQuestionAnswerPayloadMapper;

/**
 * One answer by its id: a 404 unless it is published, on a published question, about a product a
 * visitor can see. The same rule as the product page.
 *
 * @implements ProviderInterface<ProductQuestionAnswerResource>
 */
final readonly class ProductQuestionAnswerProvider implements ProviderInterface
{
    public function __construct(
        private ProductQuestionAnswerStorageInterface $answers,
        private ProductQuestionStorageInterface $storage,
        private ProductVisibilityInterface $products,
        private ProductQuestionAnswerPayloadMapper $mapper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ProductQuestionAnswerResource
    {
        $answer = $this->answers->findById((int) ($uriVariables['id'] ?? 0));

        if (null === $answer || !$answer->isPublished()) {
            return null;
        }

        $question = $this->storage->findById((int) $answer->getQuestionId());

        if (null === $question || !$question->isPublished() || !$this->products->isVisible((int) $question->getProductId())) {
            return null;
        }

        return $this->mapper->toResource($answer);
    }
}
