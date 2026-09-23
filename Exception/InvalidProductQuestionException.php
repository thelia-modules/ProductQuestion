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

namespace ProductQuestion\Exception;

/**
 * A question or an answer the module refuses to store.
 *
 * Carries the reason as a message key of the module's own translation domain, so the caller
 * decides where it is shown: a form error in the back office, a JSON error in the API.
 */
final class InvalidProductQuestionException extends \DomainException
{
    public static function emptyQuestion(): self
    {
        return new self('A question cannot be empty.');
    }

    public static function questionTooShort(int $minimum): self
    {
        return new self(\sprintf('A question must be at least %d characters long.', $minimum));
    }

    public static function questionTooLong(int $maximum): self
    {
        return new self(\sprintf('A question cannot exceed %d characters.', $maximum));
    }

    public static function emptyAnswer(): self
    {
        return new self('An answer cannot be empty.');
    }

    public static function answerTooLong(int $maximum): self
    {
        return new self(\sprintf('An answer cannot exceed %d characters.', $maximum));
    }

    public static function unknownCustomer(): self
    {
        return new self('Only a signed-in customer may ask a question.');
    }

    public static function unknownProduct(): self
    {
        return new self('A question must be about a product.');
    }

    public static function emptyLocale(): self
    {
        return new self('A question must carry the language it was asked in.');
    }
}
