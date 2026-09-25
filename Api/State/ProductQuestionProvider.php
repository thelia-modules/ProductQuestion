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

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ProductQuestion\Api\Resource\ProductQuestion as ProductQuestionResource;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Service\Api\ProductQuestionPayloadMapper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Serves the answered questions of one product, and one question by its id.
 *
 * The status filter is applied here rather than left to a query parameter: a collection a
 * client can widen is a collection that hands out what a moderator has not read yet. Same
 * for the product — the list is the questions of one product, and a request that names none
 * is refused rather than answered with the whole table.
 *
 * @implements ProviderInterface<ProductQuestionResource>
 */
final readonly class ProductQuestionProvider implements ProviderInterface
{
    public const DEFAULT_ITEMS_PER_PAGE = 20;
    public const MAX_ITEMS_PER_PAGE = 100;

    public function __construct(
        private ProductQuestionStorageInterface $storage,
        private ProductQuestionPayloadMapper $mapper,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->collection($context['filters'] ?? []);
        }

        $question = $this->storage->findById((int) ($uriVariables['id'] ?? 0));

        // Null is a 404: a question waiting for the shop, or one it refused, is not readable
        // by the visitor who happens to know its id.
        if (null === $question || !$question->isAnswered()) {
            return null;
        }

        return $this->mapper->toResource($question);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function collection(array $filters): TraversablePaginator
    {
        $productId = (int) (self::scalar($filters, 'productId') ?? 0);

        if ($productId < 1) {
            throw new BadRequestHttpException('The "productId" query parameter is required: the list is the questions of one product.');
        }

        $locale = trim(self::scalar($filters, 'locale') ?? '');

        if ('' === $locale) {
            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? '';
        }

        if ('' === $locale) {
            throw new BadRequestHttpException('The "locale" query parameter is required: a question is shown in the language it was asked in.');
        }

        $page = max(1, (int) (self::scalar($filters, 'page') ?? 1));
        $itemsPerPage = (int) (self::scalar($filters, 'itemsPerPage') ?? self::DEFAULT_ITEMS_PER_PAGE);
        $itemsPerPage = min(self::MAX_ITEMS_PER_PAGE, max(1, $itemsPerPage));

        $slice = $this->storage->findAnsweredForProductPage($productId, $locale, ($page - 1) * $itemsPerPage, $itemsPerPage);

        $questions = array_map(
            fn (ProductQuestion $question): ProductQuestionResource => $this->mapper->toResource($question),
            $slice['items'],
        );

        return new TraversablePaginator(
            new \ArrayIterator($questions),
            $page,
            $itemsPerPage,
            $slice['total'],
        );
    }

    /**
     * The filters come from parse_str, where `?locale[]=x` is an array: cast as it stands, that
     * is a warning turned 500, or a product id read off an array.
     *
     * @param array<string, mixed> $filters
     */
    private static function scalar(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!\is_scalar($value)) {
            throw new BadRequestHttpException(\sprintf('The "%s" query parameter must be a single value.', $key));
        }

        return (string) $value;
    }
}
