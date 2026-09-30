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
use ProductQuestion\Api\Resource\ProductQuestion as ProductQuestionResource;
use ProductQuestion\Exception\InvalidProductQuestionException;
use ProductQuestion\Service\Api\ProductQuestionPayloadMapper;
use ProductQuestion\Service\Front\CurrentCustomerInterface;
use ProductQuestion\Service\Front\ProductQuestionAskLimiter;
use ProductQuestion\Service\ProductQuestionAsker;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Asking a question through the front API.
 *
 * Same door as the theme's form: the customer comes from the token and never from the body,
 * the rate limiter spends its budget first, and the question is created by
 * ProductQuestionAsker rather than written here, which keeps the cleaning and the length
 * rules in one place.
 *
 * @implements ProcessorInterface<mixed, ProductQuestionResource>
 */
final readonly class ProductQuestionPostProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductQuestionAsker $asker,
        private ProductQuestionAskLimiter $askLimiter,
        private ProductQuestionPayloadMapper $mapper,
        // Wired by name on purpose: the interface's alias is the shop's session, which an
        // API request does not have. The customer is who the JWT names.
        #[Autowire(service: TokenCurrentCustomer::class)]
        private CurrentCustomerInterface $currentCustomer,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductQuestionResource
    {
        if (!$data instanceof ProductQuestionResource) {
            throw new UnprocessableEntityHttpException('A question is expected.');
        }

        // The firewall has already refused anyone else; this is what makes the rule the
        // processor's own rather than the deployment's.
        $customerId = $this->currentCustomer->id();

        if (null === $customerId) {
            throw new AccessDeniedHttpException('Only a signed-in customer may ask a question.');
        }

        $productId = (int) $data->productId;

        // Before any query: a replayed request has to cost as little as possible.
        if (!$this->askLimiter->allows($customerId, $productId)) {
            throw new TooManyRequestsHttpException(null, 'Too many questions have been sent. Please try again later.');
        }

        $locale = trim((string) $data->locale);

        if ('' === $locale) {
            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? '';
        }

        try {
            $question = $this->asker->ask($productId, $customerId, $locale, $data->content);
        } catch (InvalidProductQuestionException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return $this->mapper->toResource($question);
    }
}
