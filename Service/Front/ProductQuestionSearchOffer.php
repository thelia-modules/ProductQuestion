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

use ProductQuestion\Service\ProductQuestionSettingsInterface;

/**
 * Whether a product offers a search in its questions: the shop set a threshold, and the product
 * has more published questions than that.
 *
 * The same rule for the block and the API: below the threshold there is no search field on the
 * page, and a search the API receives is ignored rather than served, so both fronts show the
 * same list for the same product.
 */
final readonly class ProductQuestionSearchOffer
{
    public function __construct(
        private ProductQuestionSettingsInterface $settings,
    ) {
    }

    /** Whether any product may offer a search: the only question asked before a query. */
    public function isEnabled(): bool
    {
        return $this->settings->searchThreshold() > 0;
    }

    public function isOfferedFor(int $publishedQuestions): bool
    {
        return $this->isEnabled() && $publishedQuestions > $this->settings->searchThreshold();
    }
}
