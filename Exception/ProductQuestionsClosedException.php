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
 * A question or an answer about a product the shop closed, or while the whole shop is closed.
 *
 * Apart from InvalidProductQuestionException because the text may be fine: the API answers
 * it with a 403, not the 422 of an invalid text.
 */
final class ProductQuestionsClosedException extends \DomainException
{
    public static function forProduct(int $productId): self
    {
        return new self(\sprintf('Product %d does not take questions.', $productId));
    }
}
