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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * The block at the bottom of a product page, rendered through the module's real template.
 *
 * The template mounts a live component and links a stylesheet; both are Twig functions the
 * shop provides and this suite does not. They are stood in for by functions that print what
 * they were called with, so the assertions read what the hook hands to the component.
 */
final class ProductQuestionThemeHookTest extends TestCase
{
    private function hook(string $locale = 'fr_FR'): ProductQuestionThemeHook
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'ProductQuestionModule');

        $twig = new Environment($loader, ['autoescape' => 'html']);
        $twig->addFunction(new TwigFunction('component', static fn (string $name, array $props): string => $name.':'.json_encode($props, \JSON_THROW_ON_ERROR), ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('module_asset', static fn (string $module, string $path): string => '/assets/'.$module.'/'.$path));

        $requestStack = new RequestStack();

        if ('' !== $locale) {
            $request = Request::create('/');
            $request->setLocale($locale);
            $requestStack->push($request);
        }

        return new ProductQuestionThemeHook($twig, $requestStack);
    }

    public function testOnlyTheProductBottomHookIsAnswered(): void
    {
        self::assertTrue($this->hook()->supports('product.bottom'));
        self::assertFalse($this->hook()->supports('product.top'));
        self::assertFalse($this->hook()->supports('layout.body.bottom'));
    }

    /**
     * The component gets the product and the language being browsed, not the shop's default:
     * a question asked in French is answered in French.
     */
    public function testTheComponentIsMountedWithTheProductAndTheVisitorsLanguage(): void
    {
        $html = $this->hook()->render('product.bottom', ['product' => ['id' => 12]]);

        self::assertStringContainsString('ProductQuestion:{"productId":12,"locale":"fr_FR"}', $html);
        self::assertStringContainsString('ProductQuestion:{"productId":12,"locale":"en_US"}', $this->hook('en_US')->render('product.bottom', ['product' => ['id' => 12]]));
    }

    public function testTheModuleStylesheetIsLinkedByTheHook(): void
    {
        $html = $this->hook()->render('product.bottom', ['product' => ['id' => 12]]);

        self::assertStringContainsString('<link rel="stylesheet" href="/assets/ProductQuestion/assets/product-question.css">', $html);
    }

    public function testAProductObjectIsReadLikeAnArray(): void
    {
        $product = new class {
            public function getId(): int
            {
                return 7;
            }
        };

        self::assertStringContainsString('"productId":7', $this->hook()->render('product.bottom', ['product' => $product]));
    }

    public function testNoProductMeansNoBlock(): void
    {
        self::assertSame('', $this->hook()->render('product.bottom', []));
        self::assertSame('', $this->hook()->render('product.bottom', ['product' => ['id' => 0]]));
    }

    /**
     * No request means no language, and the component cannot pick one on its own.
     */
    public function testNoRequestMeansNoBlock(): void
    {
        self::assertSame('', $this->hook('')->render('product.bottom', ['product' => ['id' => 12]]));
    }
}
