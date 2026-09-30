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

use ProductQuestion\Event\ProductQuestionRefusedEvent;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Turns a question down.
 *
 * Only the status moves. An answer already written is left where it is: a refused question
 * is off the product page whatever it holds, and what a moderator typed is the trace of the
 * decision, which they may want back if they change their mind.
 */
final readonly class ProductQuestionRefuser
{
    public function __construct(
        private ProductQuestionStorageInterface $storage,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function refuse(ProductQuestion $question): ProductQuestion
    {
        $question->setStatusEnum(ProductQuestionStatus::Refused);

        $this->storage->save($question);

        $this->dispatcher->dispatch(new ProductQuestionRefusedEvent($question));

        return $question;
    }
}
