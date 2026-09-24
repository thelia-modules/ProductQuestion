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

namespace ProductQuestion;

use ProductQuestion\Install\ProductQuestionMessageInstaller;
use ProductQuestion\Repository\ProductQuestionRepository;
use ProductQuestion\Repository\ProductQuestionStorageInterface;
use ProductQuestion\Repository\ProductTitleRepository;
use ProductQuestion\Repository\ProductTitleSourceInterface;
use ProductQuestion\Service\Front\CurrentCustomerInterface;
use ProductQuestion\Service\Front\SecurityContextCurrentCustomer;
use ProductQuestion\Service\Notification\ShopContextInterface;
use ProductQuestion\Service\Notification\TheliaShopContext;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Core\Install\Database;
use Thelia\Module\BaseModule;

final class ProductQuestion extends BaseModule
{
    /**
     * The module's own strings, from I18n/{locale}.php.
     */
    public const MESSAGE_DOMAIN = 'productquestion';

    /**
     * Back-office strings, from I18n/backOffice/default-twig/{locale}.php.
     *
     * The suffix is the name of the template directory the strings sit in, not a fixed
     * word: Module::getBackOfficeTemplateTranslationDomain() builds the domain from the
     * directory it finds under templates/backOffice. Renaming that directory without
     * renaming I18n/backOffice/<same name> leaves every string untranslated, silently.
     */
    public const MESSAGE_DOMAIN_BO = 'productquestion.bo.default-twig';

    /**
     * Email strings, from I18n/email/default/{locale}.php: same rule as the back-office
     * domain, the suffix is the name of the directory under templates/email.
     */
    public const MESSAGE_DOMAIN_EMAIL = 'productquestion.email.default';

    /**
     * The message sent to the shop's notification addresses when a customer asks. A row of
     * the message table, created by ProductQuestionMessageInstaller.
     */
    public const MESSAGE_ADMIN_NOTIFICATION = 'product_question_notification_admin';

    /**
     * Where the moderation list lives: the URL the module list's Configure button points at.
     *
     * The controller redirects to it after a write, as a plain URL rather than a route name,
     * because the module's routes are resolved by a router of their own.
     */
    public const ADMIN_LIST_PATH = '/admin/module/ProductQuestion';

    public function postActivation(?ConnectionInterface $con = null): void
    {
        // Thelia stores module configuration as strings, and TheliaMain.sql drops the table
        // before creating it: replaying it on an upgrade would take every question with it.
        if ('1' !== self::getConfigValue('is_initialized', '0')) {
            (new Database($con))->insertSql(null, [__DIR__.DS.'Config'.DS.'TheliaMain.sql']);

            self::setConfigValue('is_initialized', '1');
        }

        // Outside the guard: idempotent, and a shop that activated a version without the mail
        // gets the message on the next activation as well as on update().
        (new ProductQuestionMessageInstaller())->install();
    }

    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        (new ProductQuestionMessageInstaller())->install();
    }

    /**
     * The budgets ProductQuestionAskLimiter spends on every question a customer asks.
     *
     * Declared here rather than in the shop's framework configuration so that activating the
     * module is enough. Sliding windows: a customer who hit the limit gets it back gradually
     * rather than all at once on the hour.
     */
    public static function configureContainer(ContainerConfigurator $containerConfigurator): void
    {
        $containerConfigurator->extension('framework', [
            'rate_limiter' => [
                'product_question_ask_per_customer' => [
                    'policy' => 'sliding_window',
                    'limit' => 10,
                    'interval' => '1 hour',
                ],
                'product_question_ask_per_product' => [
                    'policy' => 'sliding_window',
                    'limit' => 3,
                    'interval' => '1 hour',
                ],
            ],
        ], prepend: true);
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            // Propel models are instantiable, so load() would register each one as a
            // service. Tests/ holds doubles that implement the module's own interfaces and
            // would be autoconfigured as if they were real. The module class itself is
            // registered by Thelia, as module.ProductQuestion.
            ->exclude([
                __DIR__.'/Config/*',
                __DIR__.'/I18n/*',
                __DIR__.'/Model/*',
                __DIR__.'/Tests/*',
                __DIR__.'/ProductQuestion.php',
            ])
            ->autowire(true)
            ->autoconfigure(true);

        // load() registers a service under its class name, so autowiring the contract the
        // services depend on needs an alias of its own.
        $servicesConfigurator->alias(ProductQuestionStorageInterface::class, ProductQuestionRepository::class);
        $servicesConfigurator->alias(ProductTitleSourceInterface::class, ProductTitleRepository::class);
        $servicesConfigurator->alias(CurrentCustomerInterface::class, SecurityContextCurrentCustomer::class);
        $servicesConfigurator->alias(ShopContextInterface::class, TheliaShopContext::class);
    }
}
