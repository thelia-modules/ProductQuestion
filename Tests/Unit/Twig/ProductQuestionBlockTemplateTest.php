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

namespace ProductQuestion\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;

/**
 * What the block of a product page puts on the shop, read off the template and the component.
 *
 * Rendering a live component needs a kernel, a theme and a session, which the module's own
 * suite does not have: these read the sources instead, which is enough to catch the two
 * regressions that matter — the form drawn for a visitor, and a control that no longer
 * reaches its action.
 */
final class ProductQuestionBlockTemplateTest extends TestCase
{
    private string $template;

    private string $component;

    private string $styles;

    private string $hookTemplate;

    protected function setUp(): void
    {
        $module = \dirname(__DIR__, 3);

        $this->template = (string) file_get_contents($module.'/templates/components/ProductQuestion.html.twig');
        $this->component = (string) file_get_contents($module.'/Twig/ProductQuestionBlock.php');
        $this->styles = (string) file_get_contents($module.'/templates/frontOffice/default/assets/product-question.css');
        $this->hookTemplate = (string) file_get_contents($module.'/templates/theme_hook/product-question.html.twig');
    }

    public function testTheFormIsDrawnForASignedInCustomerOnlyAndAVisitorIsInvitedToSignIn(): void
    {
        self::assertMatchesRegularExpression('/\{% if this\.canAsk %\}.*form_start\(form.*\{% else %\}.*customer_login.*\{% endif %\}/s', $this->template);
    }

    public function testTheFormSubmitsToTheAskAction(): void
    {
        self::assertStringContainsString("'data-live-action-param': 'ask'", $this->template);
        self::assertMatchesRegularExpression('/#\[LiveAction\]\s*public function ask\(\): void/', $this->component);
    }

    /**
     * The hook links the stylesheet, and every class the template writes for its own geometry
     * is styled there: a class the theme's Tailwind build never sees is otherwise a class
     * nothing styles.
     */
    public function testEveryModuleClassOfTheTemplateIsStyledByTheModuleStylesheet(): void
    {
        self::assertStringContainsString("module_asset('ProductQuestion', 'assets/product-question.css')", $this->hookTemplate);

        preg_match_all('/\bProductQuestion-[A-Za-z]+/', $this->template, $matches);
        $classes = array_unique($matches[0]);

        self::assertNotEmpty($classes);

        foreach ($classes as $class) {
            self::assertStringContainsString('.'.$class, $this->styles, $class.' is written in the template and styled nowhere');
        }
    }

    /**
     * The stylesheet sits outside the theme's build: a utility class or an @apply here would
     * be a no-op that looks like a style.
     */
    public function testTheStylesheetUsesNoTailwindDirective(): void
    {
        self::assertStringNotContainsString('@apply', $this->styles);
        self::assertStringNotContainsString('@tailwind', $this->styles);
    }

    public function testTheHookMountsTheComponentWithTheProductAndTheLanguage(): void
    {
        self::assertStringContainsString("component('ProductQuestion', {productId: productId, locale: locale})", $this->hookTemplate);
    }
}
