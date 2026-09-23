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

use Propel\Runtime\Connection\ConnectionInterface;
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

    public function postActivation(?ConnectionInterface $con = null): void
    {
        // Thelia stores module configuration as strings, and TheliaMain.sql drops the table
        // before creating it: replaying it on an upgrade would take every question with it.
        if ('1' === self::getConfigValue('is_initialized', '0')) {
            return;
        }

        (new Database($con))->insertSql(null, [__DIR__.DS.'Config'.DS.'TheliaMain.sql']);

        self::setConfigValue('is_initialized', '1');
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
    }
}
