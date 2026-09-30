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

namespace ProductQuestion\Service\BackOffice;

use ProductQuestion\Model\ProductQuestionStatus;
use Symfony\Component\HttpFoundation\Request;

/**
 * What a moderator asked the list for.
 *
 * It holds no query: the repository owns every Propel call this module makes, and reads this
 * as a set of answers. Read from the query string, so a filtered list is a link a moderator
 * can keep, share, or come back to after acting on a question.
 */
final readonly class ProductQuestionListFilters
{
    public const DEFAULT_LIMIT = 20;

    public const MAXIMUM_LIMIT = 200;

    public const DEFAULT_ORDER = 'created_reverse';

    /** @var list<string> */
    public const ORDERS = ['created_reverse', 'created', 'status'];

    public function __construct(
        public ?int $status = null,
        public ?int $productId = null,
        public ?int $customerId = null,
        public ?string $locale = null,
        public int $page = 1,
        public int $limit = self::DEFAULT_LIMIT,
        public string $order = self::DEFAULT_ORDER,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $order = (string) $request->query->get('order', self::DEFAULT_ORDER);

        return new self(
            status: self::knownStatus($request->query->get('status')),
            productId: self::positiveInt($request->query->get('product_id')),
            customerId: self::positiveInt($request->query->get('customer_id')),
            locale: self::nonEmpty($request->query->get('locale')),
            page: max(1, (int) $request->query->get('page', 1)),
            // A limit read from the query string is a number a visitor chose. Left alone it
            // is also how one request asks the database for every row it holds.
            limit: min(self::MAXIMUM_LIMIT, max(1, (int) $request->query->get('limit', self::DEFAULT_LIMIT))),
            order: \in_array($order, self::ORDERS, true) ? $order : self::DEFAULT_ORDER,
        );
    }

    /**
     * The filters as a query string, without the page: whatever a link built from this does,
     * it starts the reading again from the first page.
     *
     * @return array<string, int|string>
     */
    public function toQueryParams(): array
    {
        $params = [];

        if (null !== $this->status) {
            $params['status'] = $this->status;
        }

        if (null !== $this->productId) {
            $params['product_id'] = $this->productId;
        }

        if (null !== $this->customerId) {
            $params['customer_id'] = $this->customerId;
        }

        if (null !== $this->locale) {
            $params['locale'] = $this->locale;
        }

        if (self::DEFAULT_ORDER !== $this->order) {
            $params['order'] = $this->order;
        }

        if (self::DEFAULT_LIMIT !== $this->limit) {
            $params['limit'] = $this->limit;
        }

        return $params;
    }

    public function withPage(int $page): self
    {
        return new self($this->status, $this->productId, $this->customerId, $this->locale, max(1, $page), $this->limit, $this->order);
    }

    public function withStatus(?int $status): self
    {
        return new self(self::knownStatus($status), $this->productId, $this->customerId, $this->locale, 1, $this->limit, $this->order);
    }

    public function withoutFilter(string $key): self
    {
        return new self(
            'status' === $key ? null : $this->status,
            'product_id' === $key ? null : $this->productId,
            'customer_id' === $key ? null : $this->customerId,
            'locale' === $key ? null : $this->locale,
            1,
            $this->limit,
            $this->order,
        );
    }

    public function hasAnyFilter(): bool
    {
        return null !== $this->status
            || null !== $this->productId
            || null !== $this->customerId
            || null !== $this->locale;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * A status the module does not know is no filter at all, rather than a filter matching
     * nothing: a hand-typed query string must not answer an empty list that looks like a
     * shop with no questions.
     */
    private static function knownStatus(mixed $raw): ?int
    {
        if (null === $raw || '' === $raw) {
            return null;
        }

        return ProductQuestionStatus::tryFrom((int) $raw)?->value;
    }

    private static function positiveInt(mixed $raw): ?int
    {
        $value = (int) $raw;

        return $value > 0 ? $value : null;
    }

    private static function nonEmpty(mixed $raw): ?string
    {
        $value = trim((string) $raw);

        return '' === $value ? null : $value;
    }
}
