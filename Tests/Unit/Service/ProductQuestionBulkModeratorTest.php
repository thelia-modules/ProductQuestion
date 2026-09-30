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

namespace ProductQuestion\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use ProductQuestion\Event\ProductQuestionRefusedEvent;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\BackOffice\BulkDecision;
use ProductQuestion\Service\BackOffice\ProductQuestionBulkModerator;
use ProductQuestion\Service\ProductQuestionPublisher;
use ProductQuestion\Service\ProductQuestionRefuser;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ProductQuestionBulkModeratorTest extends TestCase
{
    private InMemoryProductQuestionStorage $storage;

    private EventDispatcher $dispatcher;

    private ProductQuestionBulkModerator $moderator;

    protected function setUp(): void
    {
        $this->storage = new InMemoryProductQuestionStorage([
            $this->question(1),
            $this->question(2),
            $this->question(3),
        ]);
        $this->dispatcher = new EventDispatcher();
        $this->moderator = new ProductQuestionBulkModerator(
            $this->storage,
            new ProductQuestionPublisher($this->storage),
            new ProductQuestionRefuser($this->storage, $this->dispatcher),
        );
    }

    public function testTheTickedQuestionsArePublishedAndTheOthersLeftAlone(): void
    {
        self::assertSame([1, 3], $this->moderator->apply(BulkDecision::Publish, [1, 3]));

        self::assertSame(ProductQuestionStatus::Published, $this->storage->findById(1)?->getStatusEnum());
        self::assertSame(ProductQuestionStatus::Pending, $this->storage->findById(2)?->getStatusEnum());
        self::assertSame(ProductQuestionStatus::Published, $this->storage->findById(3)?->getStatusEnum());
    }

    /**
     * Refused in bulk as one by one: the event of each refusal is fired.
     */
    public function testEachRefusalFiresItsEvent(): void
    {
        $refused = [];
        $this->dispatcher->addListener(ProductQuestionRefusedEvent::class, static function (ProductQuestionRefusedEvent $event) use (&$refused): void {
            $refused[] = $event->getQuestion()->getId();
        });

        $this->moderator->apply(BulkDecision::Refuse, [2, 3]);

        self::assertSame([2, 3], $refused);
        self::assertSame(ProductQuestionStatus::Refused, $this->storage->findById(2)?->getStatusEnum());
    }

    /**
     * An id deleted since the list was drawn, or a duplicate, is neither acted on nor reported.
     */
    public function testUnknownAndRepeatedIdsAreSkipped(): void
    {
        self::assertSame([1], $this->moderator->apply(BulkDecision::Delete, [1, 1, 99, 0, -4]));

        self::assertNull($this->storage->findById(1));
        self::assertCount(1, $this->storage->deleted);
    }

    public function testABatchIsCappedAtOnePageOfTheList(): void
    {
        $ids = range(1, ProductQuestionBulkModerator::MAXIMUM_BATCH + 50);
        $storage = new InMemoryProductQuestionStorage(array_map($this->question(...), $ids));
        $moderator = new ProductQuestionBulkModerator($storage, new ProductQuestionPublisher($storage), new ProductQuestionRefuser($storage, $this->dispatcher));

        self::assertCount(ProductQuestionBulkModerator::MAXIMUM_BATCH, $moderator->apply(BulkDecision::Publish, $ids));
    }

    private function question(int $id): ProductQuestion
    {
        return (new ProductQuestion())
            ->setId($id)
            ->setProductId(12)
            ->setLocale('en_US')
            ->setContent('Question '.$id)
            ->setStatusEnum(ProductQuestionStatus::Pending);
    }
}
