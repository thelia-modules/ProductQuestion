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

namespace ProductQuestion\Service\BackOffice;

use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Service\ProductQuestionPublisher;
use ProductQuestion\Service\ProductQuestionRefuser;

/**
 * Applies one moderation decision to the questions ticked in the list.
 *
 * Question by question, through the same services as the buttons of the edit screen: a question
 * refused in bulk fires the same event as one refused alone. Returns the ids it acted on, which is
 * what the controller writes to the administration log, one line each.
 */
final readonly class ProductQuestionBulkModerator
{
    /** One page of the list at its largest: a post cannot ask for more than a moderator can tick. */
    public const MAXIMUM_BATCH = ProductQuestionListFilters::MAXIMUM_LIMIT;

    public function __construct(
        private ProductQuestionStorageInterface $storage,
        private ProductQuestionPublisher $publisher,
        private ProductQuestionRefuser $refuser,
    ) {
    }

    /**
     * @param list<int> $questionIds
     *
     * @return list<int>
     */
    public function apply(BulkDecision $decision, array $questionIds): array
    {
        $done = [];

        foreach (\array_slice(array_values(array_unique($questionIds)), 0, self::MAXIMUM_BATCH) as $questionId) {
            // Gone since the list was drawn, or never there: nothing to do, nothing to log.
            $question = $questionId > 0 ? $this->storage->findById($questionId) : null;

            if (null === $question) {
                continue;
            }

            match ($decision) {
                BulkDecision::Publish => $this->publisher->publish($question),
                BulkDecision::Refuse => $this->refuser->refuse($question),
                BulkDecision::Delete => $this->storage->delete($question),
            };

            $done[] = $questionId;
        }

        return $done;
    }
}
