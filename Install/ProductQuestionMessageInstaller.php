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

namespace ProductQuestion\Install;

use ProductQuestion\ProductQuestion as ProductQuestionModule;
use Thelia\Core\Translation\Translator;
use Thelia\Model\LangQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;

/**
 * The mail messages the module sends, as rows of the shop's message table.
 *
 * Each is created once and left alone afterwards: a subject the shop has edited in the back
 * office belongs to the shop. Run from postActivation() and from update(), so a shop that
 * activated an earlier version gets the messages it lacks on its next refresh.
 *
 * Both hooks run before the module's own catalogues are registered, so the email strings are
 * loaded by hand first; without that the titles and subjects would be stored as their keys.
 */
final class ProductQuestionMessageInstaller
{
    /**
     * name => [template base name, title key, subject key]. The subject is compiled as an
     * inline Twig template by the parser: the product title is a placeholder the listener
     * fills, see the `question.productTitle` parameter both notifications hand over.
     */
    private const MESSAGES = [
        ProductQuestionModule::MESSAGE_ADMIN_NOTIFICATION => [
            'product-question-notification-admin',
            'Notify the shop of a new customer question',
            'New customer question about "%product"',
        ],
        ProductQuestionModule::MESSAGE_CUSTOMER_ANSWERED => [
            'product-question-answered-customer',
            'Tell the customer their question has been answered',
            'Our answer to your question about "%product"',
        ],
    ];

    public function install(): void
    {
        $missing = array_filter(
            array_keys(self::MESSAGES),
            static fn (string $name): bool => null === MessageQuery::create()->findOneByName($name),
        );

        if ([] === $missing) {
            return;
        }

        $languages = LangQuery::create()->find();
        $translator = Translator::getInstance();

        foreach ($languages as $language) {
            $translator->addResource(
                'php',
                \dirname(__DIR__).'/I18n/email/default/'.$language->getLocale().'.php',
                $language->getLocale(),
                ProductQuestionModule::MESSAGE_DOMAIN_EMAIL,
            );
        }

        foreach ($missing as $name) {
            [$template, $titleKey, $subjectKey] = self::MESSAGES[$name];

            $message = new Message();
            $message
                ->setName($name)
                // No .twig in the stored names: the parser resolves ".html" and ".txt" itself.
                // No layout either: the templates extend email-layout.html.twig on their own,
                // and naming the layout here would wrap the body a second time.
                ->setHtmlTemplateFileName($template.'.html')
                ->setHtmlLayoutFileName('')
                ->setTextTemplateFileName($template.'.txt')
                ->setTextLayoutFileName('')
                ->setSecured(0);

            foreach ($languages as $language) {
                $locale = $language->getLocale();

                $message->setLocale($locale);
                $message->setTitle($translator->trans($titleKey, [], ProductQuestionModule::MESSAGE_DOMAIN_EMAIL, $locale));
                $message->setSubject($translator->trans($subjectKey, ['%product' => '{{ question.productTitle }}'], ProductQuestionModule::MESSAGE_DOMAIN_EMAIL, $locale));
            }

            $message->save();
        }
    }
}
