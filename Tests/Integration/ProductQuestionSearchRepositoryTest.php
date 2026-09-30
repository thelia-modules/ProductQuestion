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

namespace ProductQuestion\Tests\Integration;

use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionAnswer;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Repository\ProductQuestionRepository;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;

/**
 * The search of a product's questions, against the database: the question text or a published
 * answer, never a pending one, and a visitor's wildcards taken literally.
 */
final class ProductQuestionSearchRepositoryTest extends IntegrationTestCase
{
    private Product $product;

    private ProductQuestionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = $this->createFixtureFactory();
        $this->product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $this->repository = new ProductQuestionRepository();

        $this->question('Is it waterproof?');
        $this->question('How big is it?', 'Big enough, and it resists rain.');
        $this->question('Does it fold?', 'Waterproof answer still waiting.', ProductQuestionStatus::Pending);
        $this->question('Is it 100% cotton?');
        $this->question('Is it 100 percent recycled?');
    }

    public function testTheQuestionTextAndThePublishedAnswersAreSearched(): void
    {
        self::assertSame(['Is it waterproof?'], $this->found('WATERPROOF'), 'Case aside, and not through a pending answer.');
        self::assertSame(['How big is it?'], $this->found('rain'), 'Found through its published answer.');
    }

    public function testAWildcardTypedByTheVisitorIsACharacterToFind(): void
    {
        self::assertSame(['Is it 100% cotton?'], $this->found('100%'));
        self::assertSame([], $this->found('_s'));
    }

    public function testThePageCountsTheMatchesAndTheCountOfThePageDoesNot(): void
    {
        $page = $this->repository->findPublishedForProductPage((int) $this->product->getId(), 'en_US', 0, 1, 'is it');

        self::assertCount(1, $page['items']);
        self::assertSame(4, $page['total']);
        self::assertSame(5, $this->repository->countPublishedForProduct((int) $this->product->getId(), 'en_US'), 'Every published question, whatever the search.');
    }

    /**
     * @return list<string>
     */
    private function found(string $search): array
    {
        $contents = array_map(
            static fn (ProductQuestion $question): string => (string) $question->getContent(),
            $this->repository->findPublishedForProduct((int) $this->product->getId(), 'en_US', $search),
        );
        sort($contents);

        return $contents;
    }

    private function question(string $content, ?string $answer = null, ProductQuestionStatus $answerStatus = ProductQuestionStatus::Published): void
    {
        $question = (new ProductQuestion())
            ->setProductId((int) $this->product->getId())
            ->setLocale('en_US')
            ->setContent($content)
            ->setStatusEnum(ProductQuestionStatus::Published);
        $question->save();

        if (null !== $answer) {
            (new ProductQuestionAnswer())
                ->setQuestionId((int) $question->getId())
                ->setIsOfficial(true)
                ->setContent($answer)
                ->setStatusEnum($answerStatus)
                ->save();
        }
    }
}
