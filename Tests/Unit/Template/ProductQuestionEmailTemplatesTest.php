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

namespace ProductQuestion\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use ProductQuestion\Service\Notification\ProductQuestionNotification;
use ProductQuestion\Tests\Double\FixedTranslator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * The two mail templates, rendered with the parameters the listener really hands over.
 *
 * The shop's email layout is stood in for by a stub that yields the blocks the templates
 * fill: what is asserted is the module's own markup, and that a customer's text is escaped.
 */
final class ProductQuestionEmailTemplatesTest extends TestCase
{
    private function twig(): Environment
    {
        $layout = new ArrayLoader([
            'email-layout.html.twig' => '<html><title>{% block email_subject %}{% endblock %}</title><body><h1>{% block email_title %}{% endblock %}</h1>{% block email_content %}{% endblock %}</body></html>',
        ]);
        $module = new FilesystemLoader(\dirname(__DIR__, 3).'/templates/email/default');

        return new Environment(new ChainLoader([$layout, $module]), ['autoescape' => 'html', 'strict_variables' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parameters(string $content): array
    {
        return (new ProductQuestionNotification(new FixedTranslator()))->parameters(5, $content, 'fr_FR', 'Jane Doe', 'Horatio', 'https://shop.test/admin/module/ProductQuestion/5', 'fr_FR');
    }

    public function testTheHtmlMailCarriesTheQuestionAndTheLinkAndEscapesTheText(): void
    {
        $html = $this->twig()->render('product-question-notification-admin.html.twig', $this->parameters("Ligne 1\n<script>alert(1)</script>"));

        self::assertStringContainsString('<title>productquestion.email.default:New customer question about &quot;%product&quot;</title>', $html);
        self::assertStringContainsString('Ligne 1<br />', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('<a href="https://shop.test/admin/module/ProductQuestion/5">', $html);
    }

    public function testTheTextMailCarriesTheQuestionAndTheUrl(): void
    {
        $text = $this->twig()->render('product-question-notification-admin.txt.twig', $this->parameters('Est-ce compatible ?'));

        self::assertStringContainsString('Est-ce compatible ?', $text);
        self::assertStringContainsString('Answer it from the back office: %url', $text);
        self::assertStringNotContainsString('<', $text);
    }
}
