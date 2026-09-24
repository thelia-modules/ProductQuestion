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
 * The mail message the module sends, as a row of the shop's message table.
 *
 * Created once and left alone afterwards: a subject the shop has edited in the back office
 * belongs to the shop. Run from postActivation() and from update(), so a shop that activated
 * an earlier version gets the message on its next refresh.
 *
 * Both hooks run before the module's own catalogues are registered, so the email strings are
 * loaded by hand first; without that the title and subject would be stored as their keys.
 */
final class ProductQuestionMessageInstaller
{
    public function install(): void
    {
        if (null !== MessageQuery::create()->findOneByName(ProductQuestionModule::MESSAGE_ADMIN_NOTIFICATION)) {
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

        $message = new Message();
        $message
            ->setName(ProductQuestionModule::MESSAGE_ADMIN_NOTIFICATION)
            // No .twig in the stored names: the parser resolves ".html" and ".txt" itself. No
            // layout either: the templates extend email-layout.html.twig on their own, and
            // naming the layout here would wrap the body a second time.
            ->setHtmlTemplateFileName('product-question-notification-admin.html')
            ->setHtmlLayoutFileName('')
            ->setTextTemplateFileName('product-question-notification-admin.txt')
            ->setTextLayoutFileName('')
            ->setSecured(0);

        foreach ($languages as $language) {
            $locale = $language->getLocale();

            $message->setLocale($locale);
            $message->setTitle($translator->trans('Notify the shop of a new customer question', [], ProductQuestionModule::MESSAGE_DOMAIN_EMAIL, $locale));
            // The subject is compiled as an inline Twig template by the parser: the product
            // title is a placeholder the listener fills, see the labels it hands over.
            $message->setSubject($translator->trans('New customer question about "%product"', ['%product' => '{{ question.productTitle }}'], ProductQuestionModule::MESSAGE_DOMAIN_EMAIL, $locale));
        }

        $message->save();
    }
}
