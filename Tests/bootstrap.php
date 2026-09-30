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

$moduleDir = dirname(__DIR__);

/*
 * 1. The Composer autoloader of the Thelia install this module sits in. Point
 *    THELIA_VENDOR_AUTOLOAD at it to run the tests from a checkout with no install above
 *    it; otherwise it is looked up in the parent directories, which covers both
 *    local/modules/ProductQuestion and vendor/thelia/modules/ProductQuestion.
 */
$autoload = getenv('THELIA_VENDOR_AUTOLOAD') ?: null;

if (null === $autoload) {
    $candidate = $moduleDir;

    while ('/' !== $candidate && strlen($candidate) > 1) {
        if (is_file($candidate.'/vendor/autoload.php')) {
            $autoload = $candidate.'/vendor/autoload.php';
            break;
        }

        $candidate = dirname($candidate);
    }
}

if (null === $autoload || !is_file($autoload)) {
    fwrite(
        \STDERR,
        "Cannot find the Thelia Composer autoloader.\n"
        ."Run the tests from inside a Thelia install, or set THELIA_VENDOR_AUTOLOAD to its vendor/autoload.php.\n"
    );

    exit(1);
}

require $autoload;

/*
 * 2. The module's own classes are in no Composer autoload map: at runtime the kernel
 *    registers them when it activates the module. Nothing does that here.
 */
spl_autoload_register(static function (string $class) use ($moduleDir): void {
    if (!str_starts_with($class, 'ProductQuestion\\')) {
        return;
    }

    $file = $moduleDir.'/'.str_replace('\\', '/', substr($class, strlen('ProductQuestion\\'))).'.php';

    if (is_file($file)) {
        require $file;
    }
});

/*
 * 3. ProductQuestion\Model\ProductQuestion extends a Propel base class that exists only once
 *    Propel has built the model tree into var/propel/<env>/model. These tests are meant to
 *    run without a built install, so the base class comes from a stand-in carrying the
 *    columns of Config/schema.xml, which records saves instead of writing them.
 *
 *    It is required before anything can reach the autoloader above, so the stand-in always
 *    wins over a generated tree that may or may not be in the include path.
 */
require __DIR__.'/Double/PropelBase/ProductQuestion.php';
