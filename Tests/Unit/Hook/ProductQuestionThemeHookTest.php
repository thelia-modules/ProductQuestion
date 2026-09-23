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

namespace ProductQuestion\Tests\Unit\Hook;

use PHPUnit\Framework\TestCase;
use ProductQuestion\Hook\Theme\ProductQuestionThemeHook;
use ProductQuestion\Model\ProductQuestion;
use ProductQuestion\Model\ProductQuestionStatus;
use ProductQuestion\Tests\Double\FixedTranslator;
use ProductQuestion\Tests\Double\InMemoryProductQuestionStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The block at the bottom of a product page, rendered through the module's real template.
 *
 * Nothing here is mocked away from the output: what the assertions read is the HTML a
 * visitor would be served.
 */
final class ProductQuestionThemeHookTest extends TestCase
{
    private InMemoryProductQuestionStorage $storage;

    private function hook(string $locale = 'fr_FR'): ProductQuestionThemeHook
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'ProductQuestionModule');

        $requestStack = new RequestStack();

        if ('' !== $locale) {
            $request = Request::create('/');
            $request->setLocale($locale);
            $requestStack->push($request);
        }

        return new ProductQuestionThemeHook(
            new Environment($loader, ['autoescape' => 'html']),
            $this->storage,
            $requestStack,
            new FixedTranslator(),
        );
    }

    private function question(int $productId, string $locale, ProductQuestionStatus $status, string $content, ?string $answer = null): ProductQuestion
    {
        $question = new ProductQuestion();
        $question
            ->setProductId($productId)
            ->setCustomerId(1)
            ->setLocale($locale)
            ->setContent($content)
            ->setAnswer($answer)
            ->setAnsweredAt(null === $answer ? null : new \DateTimeImmutable('2026-01-15 10:00:00'))
            ->setStatusEnum($status);

        return $question;
    }

    public function testOnlyTheProductBottomHookIsAnswered(): void
    {
        $this->storage = new InMemoryProductQuestionStorage();

        self::assertTrue($this->hook()->supports('product.bottom'));
        self::assertFalse($this->hook()->supports('product.top'));
        self::assertFalse($this->hook()->supports('layout.body.bottom'));
    }

    /**
     * A product nobody has asked about renders nothing at all, rather than a heading with an
     * empty list under it.
     */
    public function testAProductWithNoAnsweredQuestionRendersNothing(): void
    {
        $this->storage = new InMemoryProductQuestionStorage([
            $this->question(12, 'fr_FR', ProductQuestionStatus::Pending, 'En attente ?'),
            $this->question(12, 'fr_FR', ProductQuestionStatus::Refused, 'Refusee ?'),
        ]);

        self::assertSame('', $this->hook()->render('product.bottom', ['product' => ['id' => 12]]));
    }

    public function testAnAnsweredQuestionIsRenderedWithItsAnswer(): void
    {
        $this->storage = new InMemoryProductQuestionStorage([
            $this->question(12, 'fr_FR', ProductQuestionStatus::Answered, 'Est-ce compatible ?', 'Oui, compatible.'),
        ]);

        $html = $this->hook()->render('product.bottom', ['product' => ['id' => 12]]);

        self::assertStringContainsString('Est-ce compatible ?', $html);
        self::assertStringContainsString('Oui, compatible.', $html);
        self::assertStringContainsString('2026-01-15', $html);
    }

    /**
     * A question asked in one language is answered in that language. Showing it to a visitor
     * browsing another is showing them text they did not ask for and may not read.
     */
    public function testAQuestionInAnotherLanguageIsNotShown(): void
    {
        $this->storage = new InMemoryProductQuestionStorage([
            $this->question(12, 'en_US', ProductQuestionStatus::Answered, 'Is it compatible?', 'Yes it is.'),
        ]);

        self::assertSame('', $this->hook()->render('product.bottom', ['product' => ['id' => 12]]));
        self::assertStringContainsString('Is it compatible?', $this->hook('en_US')->render('product.bottom', ['product' => ['id' => 12]]));
    }

    public function testAQuestionOfAnotherProductIsNotShown(): void
    {
        $this->storage = new InMemoryProductQuestionStorage([
            $this->question(99, 'fr_FR', ProductQuestionStatus::Answered, 'Autre produit ?', 'Oui.'),
        ]);

        self::assertSame('', $this->hook()->render('product.bottom', ['product' => ['id' => 12]]));
    }

    public function testNoProductMeansNoBlock(): void
    {
        $this->storage = new InMemoryProductQuestionStorage([
            $this->question(12, 'fr_FR', ProductQuestionStatus::Answered, 'Est-ce compatible ?', 'Oui.'),
        ]);

        self::assertSame('', $this->hook()->render('product.bottom', []));
    }

    /**
     * The text is stored as the visitor typed it. What keeps it from becoming markup on the
     * page is the escaping, so the template must never hand it over raw.
     */
    public function testTheStoredTextIsEscapedOnTheWayOut(): void
    {
        $this->storage = new InMemoryProductQuestionStorage([
            $this->question(12, 'fr_FR', ProductQuestionStatus::Answered, 'Poids < 6 kg ?', 'Oui & confirme.'),
        ]);

        $html = $this->hook()->render('product.bottom', ['product' => ['id' => 12]]);

        self::assertStringContainsString('Poids &lt; 6 kg ?', $html);
        self::assertStringContainsString('Oui &amp; confirme.', $html);
    }

    /**
     * A product object rather than an array is what the Flexy theme hands the hook.
     */
    public function testTheProductMayComeAsAnObject(): void
    {
        $this->storage = new InMemoryProductQuestionStorage([
            $this->question(12, 'fr_FR', ProductQuestionStatus::Answered, 'Est-ce compatible ?', 'Oui.'),
        ]);

        $product = new class {
            public function getId(): int
            {
                return 12;
            }
        };

        self::assertStringContainsString(
            'Est-ce compatible ?',
            $this->hook()->render('product.bottom', ['product' => $product])
        );
    }
}
