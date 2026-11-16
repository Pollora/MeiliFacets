<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/*
 * ARCH-07: the harness preset (WordPress classes and functions, the tests), plus what is specific to this module.
 */
$configuration = require __DIR__.'/vendor/pollora/harness/presets/composer-dependency-analyser.php';

return $configuration
    // The module runs inside a Pollora host, which provides WooCommerce, Yoast SEO, nwidart/laravel-modules, the Laravel
    // HTTP kernel and Pollora's own packages; none is a dependency of the module.
    ->ignoreUnknownClassesRegex('~^(WC_|Yoast\\\\|Automattic\\\\|Nwidart\\\\|Illuminate\\\\(Foundation|Http)\\\\|Symfony\\\\Component\\\\HttpFoundation\\\\)~')
    // What illuminate/support already requires (collections, container) and the Finder of the Symfony components Laravel ships.
    ->ignoreErrorsOnPackages(['illuminate/collections', 'illuminate/container', 'symfony/finder'], [ErrorType::SHADOW_DEPENDENCY])
    // Optional by design: composer.json suggests it, and NameOrder falls back to a byte comparison without it.
    ->ignoreErrorsOnExtension('ext-intl', [ErrorType::SHADOW_DEPENDENCY])
    // An installer plugin, which Composer runs and the code never names; and the library Illuminate\Support\Uri is built on.
    ->ignoreErrorsOnPackages(['composer/installers', 'league/uri'], [ErrorType::UNUSED_DEPENDENCY])
    // Pollora's attributes and discovery come with the framework of the host. They are in require-dev only so that
    // PHPStan and the tests can see them; requiring them is a change of the module's runtime dependencies to decide.
    ->ignoreErrorsOnPackages(['pollora/attributes', 'pollora/discovery', 'spatie/php-structure-discoverer'], [ErrorType::DEV_DEPENDENCY_IN_PROD, ErrorType::SHADOW_DEPENDENCY]);
