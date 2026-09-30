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
 * Stand-in for the Propel base class of ProductQuestion\Model\ProductQuestionAnswer, tests
 * only, on the same terms as the one of ProductQuestion: signatures copied from the generated
 * class, save() and delete() recorded instead of written.
 */
class ProductQuestionAnswer
{
    protected ?int $id = null;
    protected ?int $question_id = null;
    protected ?int $customer_id = null;
    protected ?int $admin_id = null;
    protected ?bool $is_official = false;
    protected ?string $content = null;
    protected ?int $status = 0;
    protected ?int $helpful_count = 0;
    protected string|int|\DateTimeInterface|null $published_at = null;
    protected string|int|\DateTimeInterface|null $created_at = null;
    protected string|int|\DateTimeInterface|null $updated_at = null;

    public int $saveCount = 0;

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

    public function getQuestionId(): ?int
    {
        return $this->question_id;
    }

    public function setQuestionId(?int $v = null): static
    {
        $this->question_id = $v;

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

    public function getAdminId(): ?int
    {
        return $this->admin_id;
    }

    public function setAdminId(?int $v = null): static
    {
        $this->admin_id = $v;

        return $this;
    }

    public function getIsOfficial(): ?bool
    {
        return $this->is_official;
    }

    public function setIsOfficial(mixed $v): static
    {
        $this->is_official = null === $v ? null : (bool) $v;

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

    public function getHelpfulCount(): ?int
    {
        return $this->helpful_count;
    }

    public function setHelpfulCount(?int $v = null): static
    {
        $this->helpful_count = $v;

        return $this;
    }

    public function getPublishedAt(?string $format = null): string|\DateTimeInterface|null
    {
        if (null === $this->published_at || null === $format) {
            return $this->published_at;
        }

        $date = $this->published_at instanceof \DateTimeInterface ? $this->published_at : new \DateTimeImmutable((string) $this->published_at);

        return $date->format($format);
    }

    public function setPublishedAt(string|int|\DateTimeInterface|null $v = null): static
    {
        $this->published_at = $v;

        return $this;
    }

    public function getCreatedAt(?string $format = null): string|\DateTimeInterface|null
    {
        if (null === $this->created_at || null === $format) {
            return $this->created_at;
        }

        $date = $this->created_at instanceof \DateTimeInterface ? $this->created_at : new \DateTimeImmutable((string) $this->created_at);

        return $date->format($format);
    }

    public function setCreatedAt(string|int|\DateTimeInterface|null $v = null): static
    {
        $this->created_at = $v;

        return $this;
    }

    public function getUpdatedAt(?string $format = null): string|\DateTimeInterface|null
    {
        if (null === $this->updated_at || null === $format) {
            return $this->updated_at;
        }

        $date = $this->updated_at instanceof \DateTimeInterface ? $this->updated_at : new \DateTimeImmutable((string) $this->updated_at);

        return $date->format($format);
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
}
