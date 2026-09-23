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

namespace ProductQuestion\Repository;

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Service\BackOffice\ProductQuestionListFilters;

/**
 * Every read and write the module's services make, behind a contract.
 *
 * The services carry the rules a shop's data depends on. Reaching for ProductQuestionQuery
 * inside them would make those rules impossible to exercise without a built Propel model
 * tree and a database, so they go through this interface and ProductQuestionRepository is
 * the one implementation.
 */
interface ProductQuestionStorageInterface
{
    public function findById(int $id): ?ProductQuestion;

    /**
     * The answered questions of one product, in one language, most recently answered first.
     *
     * Neither the status nor the language is optional. This is what a visitor may read, and
     * the front office reads nothing else: a pending or refused question must never become
     * readable because a query parameter asked for it.
     *
     * @return list<ProductQuestion>
     */
    public function findAnsweredForProduct(int $productId, string $locale): array;

    /**
     * Every question one customer asked, whatever its status, oldest first: what a personal
     * data export has to carry and what anonymizing an account has to go through.
     *
     * @return list<ProductQuestion>
     */
    public function findByCustomer(int $customerId): array;

    /**
     * How many questions sit in each status, keyed by the stored integer.
     *
     * A status nobody has a question in is absent from the result rather than present with a
     * zero: the caller knows the statuses it wants to show.
     *
     * @return array<int, int>
     */
    public function countByStatus(): array;

    /**
     * One page of the moderation list, and how many rows the filters match in total.
     *
     * Unlike the front-office read, every status is reachable here: this is the screen a
     * moderator uses to find what is waiting for them.
     *
     * @return array{items: list<ProductQuestion>, total: int}
     */
    public function searchForModeration(ProductQuestionListFilters $filters): array;

    /**
     * The languages questions have actually been asked in, sorted.
     *
     * The filter offers these rather than every language the shop declares: a language
     * nobody has written in would filter to an empty list every time.
     *
     * @return list<string>
     */
    public function findUsedLocales(): array;

    public function save(ProductQuestion $question): void;

    public function delete(ProductQuestion $question): void;
}
