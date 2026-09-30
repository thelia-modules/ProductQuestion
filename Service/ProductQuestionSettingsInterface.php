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

/**
 * The shop's choices about the questions block, as the services read them.
 *
 * Behind a contract so that the rules which depend on a setting run in a unit test with the
 * setting either way. Stored in the module's configuration, set from the moderation screen.
 */
interface ProductQuestionSettingsInterface
{
    /**
     * Whether customers may answer each other's published questions. Off until the shop turns it
     * on: an open answer form is one more thing to moderate.
     */
    public function allowsCustomerAnswers(): bool;

    public function setAllowsCustomerAnswers(bool $allowed): void;

    /**
     * Whether the whole shop is closed to new questions. The published questions stay on the
     * product pages; only asking and answering stop. Open until the shop closes it.
     */
    public function questionsClosed(): bool;

    public function setQuestionsClosed(bool $closed): void;

    /**
     * How many questions the product page shows before offering the next ones. 0 shows them all
     * on one page, as the module always did.
     */
    public function questionsPerPage(): int;

    public function setQuestionsPerPage(int $perPage): void;

    /**
     * Above how many published questions a product offers a search in them. 0 never offers one,
     * as the module always did.
     */
    public function searchThreshold(): int;

    public function setSearchThreshold(int $threshold): void;

    /**
     * Whether a product page shows the published questions of every language, each with the
     * language it was asked in. Off: the page shows those of the language being browsed only, as
     * the module always did.
     */
    public function showsAllLanguages(): bool;

    public function setShowsAllLanguages(bool $all): void;
}
