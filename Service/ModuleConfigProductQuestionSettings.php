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
}
