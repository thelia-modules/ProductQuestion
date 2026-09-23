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
use ProductQuestion\Event\ProductQuestionCreatedEvent;
use ProductQuestion\Exception\InvalidProductQuestionException;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Service\Front\ProductQuestionTextSanitizer;
use ProductQuestion\Service\ProductQuestionAsker;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ProductQuestionAskerTest extends TestCase
{
    private InMemoryProductQuestionStorage $storage;

    private EventDispatcher $dispatcher;

    private ProductQuestionAsker $asker;

    protected function setUp(): void
    {
        $this->storage = new InMemoryProductQuestionStorage();
        $this->dispatcher = new EventDispatcher();
        $this->asker = new ProductQuestionAsker(
            $this->storage,
            new ProductQuestionTextSanitizer(),
            $this->dispatcher,
        );
    }

    /**
     * The one state this service writes. Nothing a visitor sends reaches the product page
     * before an administrator has answered it.
     */
    public function testAQuestionIsStoredPending(): void
    {
        $question = $this->asker->ask(12, 34, 'fr_FR', 'Est-ce compatible avec le modele 2024 ?');

        self::assertSame(ProductQuestionStatus::Pending, $question->getStatusEnum());
        self::assertSame(12, $question->getProductId());
        self::assertSame(34, $question->getCustomerId());
        self::assertSame('fr_FR', $question->getLocale());
        self::assertCount(1, $this->storage->saved);
    }

    public function testTheStoredQuestionIsTheCleanedText(): void
    {
        $question = $this->asker->ask(12, 34, 'fr_FR', '  Est-ce <b>compatible</b> ?  ');

        self::assertSame('Est-ce compatible ?', $question->getContent());
    }

    /**
     * Dispatched after the row is written, so the administrator notification of a later phase
     * has an identified question to name.
     */
    public function testTheCreatedEventCarriesTheSavedQuestion(): void
    {
        $seen = [];
        $this->dispatcher->addListener(
            ProductQuestionCreatedEvent::class,
            static function (ProductQuestionCreatedEvent $event) use (&$seen): void {
                $seen[] = $event->getQuestion();
            }
        );

        $question = $this->asker->ask(12, 34, 'fr_FR', 'Une question valable ?');

        self::assertSame([$question], $seen);
        self::assertNotNull($question->getId());
    }

    public function testAnAnonymousVisitorCannotAsk(): void
    {
        $this->expectException(InvalidProductQuestionException::class);

        $this->asker->ask(12, 0, 'fr_FR', 'Une question valable ?');
    }

    public function testAQuestionAboutNoProductIsRefused(): void
    {
        $this->expectException(InvalidProductQuestionException::class);

        $this->asker->ask(0, 34, 'fr_FR', 'Une question valable ?');
    }

    public function testAQuestionWithoutALanguageIsRefused(): void
    {
        $this->expectException(InvalidProductQuestionException::class);

        $this->asker->ask(12, 34, '  ', 'Une question valable ?');
    }

    /**
     * Markup alone leaves nothing behind once cleaned, and an empty question must not reach
     * a moderator's list.
     */
    public function testTextThatCleansDownToNothingIsRefused(): void
    {
        $this->expectException(InvalidProductQuestionException::class);

        $this->asker->ask(12, 34, 'fr_FR', '<b></b>   ');
    }

    public function testATooShortQuestionIsRefused(): void
    {
        $this->expectException(InvalidProductQuestionException::class);

        $this->asker->ask(12, 34, 'fr_FR', 'hmm');
    }

    /**
     * The limit is counted in characters: an accented question is not worth two thirds of a
     * plain one.
     */
    public function testTheLengthLimitIsCountedInCharactersNotBytes(): void
    {
        $accented = str_repeat('é', ProductQuestionAsker::MAXIMUM_LENGTH);

        $question = $this->asker->ask(12, 34, 'fr_FR', $accented);

        self::assertSame(ProductQuestionAsker::MAXIMUM_LENGTH, mb_strlen((string) $question->getContent()));

        $this->expectException(InvalidProductQuestionException::class);

        $this->asker->ask(12, 34, 'fr_FR', $accented.'é');
    }

    public function testNothingIsSavedWhenTheQuestionIsRefused(): void
    {
        try {
            $this->asker->ask(12, 0, 'fr_FR', 'Une question valable ?');
        } catch (InvalidProductQuestionException) {
            // The point of the test is what did not happen.
        }

        self::assertSame([], $this->storage->saved);
    }
}
