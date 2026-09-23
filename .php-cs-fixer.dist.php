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

/*
 * The rule set of thelia/thelia. The header is the one php-cs-fixer writes and keeps in
 * order: the boxed header older Thelia modules carry does not survive @Symfony, which
 * breaks it into several comment blocks on the first run.
 */

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor'])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setCacheFile(__DIR__.'/.phpunit.cache/.php_cs.cache')
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP80Migration' => true,
        '@PHP80Migration:risky' => true,
        '@PHP81Migration' => true,
        '@PHP82Migration' => true,
        '@PHP83Migration' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'yoda_style' => false,
        'declare_strict_types' => true,
        'header_comment' => [
            'header' => implode("\n", [
                'This file is part of the ProductQuestion module for Thelia 3.',
                '',
                '(c) OpenStudio <info@thelia.net>',
                '',
                'For the full copyright and license information, please view the LICENSE',
                'file that was distributed with this source code.',
            ]),
        ],
    ])
    ->setFinder($finder);
