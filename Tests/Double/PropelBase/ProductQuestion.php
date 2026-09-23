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

namespace ProductQuestion\Model\Base;

/**
 * Stand-in for the Propel base class of ProductQuestion\Model\ProductQuestion, tests only.
 *
 * Propel builds the real one into var/propel/<env>/model from Config/schema.xml when the
 * module is activated, so it is in no checkout and in no fresh clone. Every signature below
 * is copied from that generated class: nullable throughout, `static` returned by each
 * setter, INTEGER and TINYINT columns exposed as `?int` — which is what turns a `true`
 * passed to setStatus() into a TypeError in production.
 *
 * save() and delete() record the call instead of reaching a database, so a test can assert
 * what the module decided to persist without one.
 */
class ProductQuestion
{
    protected ?int $id = null;
    protected ?int $product_id = null;
    protected ?int $customer_id = null;
    protected ?string $locale = null;
    protected ?string $content = null;
    protected ?int $status = 0;
    protected ?string $answer = null;
    protected string|int|\DateTimeInterface|null $answered_at = null;
    protected ?int $answered_by = null;
    protected string|int|\DateTimeInterface|null $created_at = null;
    protected string|int|\DateTimeInterface|null $updated_at = null;

    /** How many times save() was called on this object. */
    public int $saveCount = 0;

    /** How many times delete() was called on this object. */
    public int $deleteCount = 0;

    private bool $new = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $v = null): static
    {
        $this->id = $v;

        return $this;
    }

    public function getProductId(): ?int
    {
        return $this->product_id;
    }

    public function setProductId(?int $v = null): static
    {
        $this->product_id = $v;

        return $this;
    }

    public function getCustomerId(): ?int
    {
        return $this->customer_id;
    }

    public function setCustomerId(?int $v = null): static
    {
        $this->customer_id = $v;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $v = null): static
    {
        $this->locale = $v;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $v = null): static
    {
        $this->content = $v;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(?int $v = null): static
    {
        $this->status = $v;

        return $this;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(?string $v = null): static
    {
        $this->answer = $v;

        return $this;
    }

    public function getAnsweredAt(?string $format = null): string|\DateTimeInterface|null
    {
        return $this->formatDate($this->answered_at, $format);
    }

    public function setAnsweredAt(string|int|\DateTimeInterface|null $v = null): static
    {
        $this->answered_at = $v;

        return $this;
    }

    public function getAnsweredBy(): ?int
    {
        return $this->answered_by;
    }

    public function setAnsweredBy(?int $v = null): static
    {
        $this->answered_by = $v;

        return $this;
    }

    public function getCreatedAt(?string $format = null): string|\DateTimeInterface|null
    {
        return $this->formatDate($this->created_at, $format);
    }

    public function setCreatedAt(string|int|\DateTimeInterface|null $v = null): static
    {
        $this->created_at = $v;

        return $this;
    }

    public function getUpdatedAt(?string $format = null): string|\DateTimeInterface|null
    {
        return $this->formatDate($this->updated_at, $format);
    }

    public function setUpdatedAt(string|int|\DateTimeInterface|null $v = null): static
    {
        $this->updated_at = $v;

        return $this;
    }

    public function isNew(): bool
    {
        return $this->new;
    }

    public function setNew(bool $b): void
    {
        $this->new = $b;
    }

    public function save(mixed $con = null): int
    {
        ++$this->saveCount;
        $this->new = false;

        return 1;
    }

    public function delete(mixed $con = null): void
    {
        ++$this->deleteCount;
    }

    private function formatDate(string|int|\DateTimeInterface|null $value, ?string $format): string|\DateTimeInterface|null
    {
        if (null === $value || null === $format) {
            return $value;
        }

        $date = $value instanceof \DateTimeInterface ? $value : new \DateTimeImmutable((string) $value);

        return $date->format($format);
    }
}
