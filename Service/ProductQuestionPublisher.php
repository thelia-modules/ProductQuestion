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

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ProductQuestionStorageInterface;

/**
 * Puts a question on the product page without answering it, so that customers can.
 *
 * Answering from the back office publishes the question as well; this is the other way in, for a
 * shop that lets its customers answer.
 */
final readonly class ProductQuestionPublisher
{
    public function __construct(
        private ProductQuestionStorageInterface $storage,
    ) {
    }

    public function publish(ProductQuestion $question): void
    {
        if ($question->isPublished()) {
            return;
        }

        $question->setStatusEnum(ProductQuestionStatus::Published);

        $this->storage->save($question);
    }
}
