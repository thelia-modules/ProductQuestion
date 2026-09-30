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

namespace ProductQuestion\Service;

use ProductQuestion\ProductQuestion;

final readonly class ModuleConfigProductQuestionSettings implements ProductQuestionSettingsInterface
{
    public const ALLOW_CUSTOMER_ANSWERS = 'allow_customer_answers';
    public const QUESTIONS_CLOSED = 'questions_closed';
    public const QUESTIONS_PER_PAGE = 'questions_per_page';

    /** A page larger than this is a page nobody reads, and a query nobody needs. */
    public const MAXIMUM_PER_PAGE = 100;

    public function allowsCustomerAnswers(): bool
    {
        // Stored as the string '1' or '0': a cast to bool of '0' is false, of a missing value too.
        return '1' === ProductQuestion::getConfigValue(self::ALLOW_CUSTOMER_ANSWERS, '0');
    }

    public function setAllowsCustomerAnswers(bool $allowed): void
    {
        ProductQuestion::setConfigValue(self::ALLOW_CUSTOMER_ANSWERS, $allowed ? '1' : '0');
    }

    public function questionsClosed(): bool
    {
        return '1' === ProductQuestion::getConfigValue(self::QUESTIONS_CLOSED, '0');
    }

    public function setQuestionsClosed(bool $closed): void
    {
        ProductQuestion::setConfigValue(self::QUESTIONS_CLOSED, $closed ? '1' : '0');
    }

    public function questionsPerPage(): int
    {
        return self::bounded(ProductQuestion::getConfigValue(self::QUESTIONS_PER_PAGE, '0'), self::MAXIMUM_PER_PAGE);
    }

    public function setQuestionsPerPage(int $perPage): void
    {
        ProductQuestion::setConfigValue(self::QUESTIONS_PER_PAGE, (string) self::bounded((string) $perPage, self::MAXIMUM_PER_PAGE));
    }

    /**
     * A stored number read back between 0 and the maximum: a value typed by hand in the module
     * configuration is not trusted to be one.
     */
    private static function bounded(?string $value, int $maximum): int
    {
        if (null === $value || !ctype_digit($value)) {
            return 0;
        }

        return min($maximum, (int) $value);
    }
}
