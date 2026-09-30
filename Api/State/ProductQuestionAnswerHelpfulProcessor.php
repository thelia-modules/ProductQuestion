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
use ApiPlatform\State\ProcessorInterface;
use ProductQuestion\Api\Resource\ProductQuestionAnswer as ProductQuestionAnswerResource;
use ProductQuestion\Exception\InvalidProductQuestionException;
use ProductQuestion\Repository\ProductQuestionAnswerStorageInterface;
use ProductQuestion\Service\Api\ProductQuestionAnswerPayloadMapper;
use ProductQuestion\Service\Front\CurrentCustomerInterface;
use ProductQuestion\Service\ProductQuestionHelpfulVoter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A customer saying an answer helped them, through the front API: counted once whatever the
 * number of calls, by ProductQuestionHelpfulVoter.
 *
 * @implements ProcessorInterface<mixed, ProductQuestionAnswerResource>
 */
final readonly class ProductQuestionAnswerHelpfulProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductQuestionHelpfulVoter $voter,
        private ProductQuestionAnswerStorageInterface $answers,
        private ProductQuestionAnswerPayloadMapper $mapper,
        #[Autowire(service: TokenCurrentCustomer::class)]
        private CurrentCustomerInterface $currentCustomer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductQuestionAnswerResource
    {
        $customerId = $this->currentCustomer->id();

        if (null === $customerId) {
            throw new AccessDeniedHttpException('Only a signed-in customer may vote.');
        }

        $answerId = (int) ($uriVariables['id'] ?? 0);

        try {
            $this->voter->vote($answerId, $customerId);
        } catch (InvalidProductQuestionException $exception) {
            // Unreadable and one's own alike: nothing tells which answers exist off the page.
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        $answer = $this->answers->findById($answerId) ?? throw new NotFoundHttpException();

        return $this->mapper->toResource($answer);
    }
}
