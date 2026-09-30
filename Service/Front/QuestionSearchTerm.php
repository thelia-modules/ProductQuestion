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

namespace ProductQuestion\Service\Front;

/**
 * What a visitor typed in the search field of a product's questions, made into a term the
 * repository can match, or null when there is nothing to search for.
 */
final class QuestionSearchTerm
{
    /** One letter matches everything and says nothing. */
    public const MINIMUM_LENGTH = 2;

    /** A search field, not a question: longer than this is cut. */
    public const MAXIMUM_LENGTH = 100;

    public static function normalize(mixed $typed): ?string
    {
        if (!\is_string($typed)) {
            return null;
        }

        // Tags stripped as the stored texts were, spaces collapsed: "  fold  " finds "fold".
        $term = trim((string) preg_replace('/\s+/u', ' ', strip_tags($typed)));
        $term = mb_substr($term, 0, self::MAXIMUM_LENGTH);

        return mb_strlen($term) < self::MINIMUM_LENGTH ? null : $term;
    }

    /**
     * The term as a LIKE pattern: its own % and _ are literal characters, not wildcards.
     */
    public static function likePattern(string $term): string
    {
        return '%'.addcslashes($term, '\\%_').'%';
    }
}
