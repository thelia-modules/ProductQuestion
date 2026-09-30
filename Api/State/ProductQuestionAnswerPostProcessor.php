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
use ProductQuestion\Service\Api\ProductQuestionAnswerPayloadMapper;
use ProductQuestion\Service\Front\CurrentCustomerInterface;
use ProductQuestion\Service\Front\ProductQuestionAnswerLimiter;
use ProductQuestion\Service\ProductQuestionCustomerAnswerer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * A customer answering a question through the front API, by the same door as the theme's form:
 * the customer comes from the token, the budget is spent before any query, and the answer is
 * written by ProductQuestionCustomerAnswerer, pending.
 *
 * @implements ProcessorInterface<mixed, ProductQuestionAnswerResource>
 */
final readonly class ProductQuestionAnswerPostProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductQuestionCustomerAnswerer $answerer,
        private ProductQuestionAnswerLimiter $limiter,
        private ProductQuestionAnswerPayloadMapper $mapper,
        #[Autowire(service: TokenCurrentCustomer::class)]
        private CurrentCustomerInterface $currentCustomer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductQuestionAnswerResource
    {
        if (!$data instanceof ProductQuestionAnswerResource) {
            throw new UnprocessableEntityHttpException('An answer is expected.');
        }

        $customerId = $this->currentCustomer->id();

        if (null === $customerId) {
            throw new AccessDeniedHttpException('Only a signed-in customer may answer a question.');
        }

        if (!$this->limiter->allows($customerId)) {
            throw new TooManyRequestsHttpException(null, 'Too many answers have been sent. Please try again later.');
        }

        try {
            $answer = $this->answerer->answer((int) $data->questionId, $customerId, $data->content);
        } catch (InvalidProductQuestionException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return $this->mapper->toResource($answer);
    }
}
