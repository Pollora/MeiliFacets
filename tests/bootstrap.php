<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

// The module writes its user-facing strings with `__()`, which a host always provides —
// WordPress natively, or `pollora/helper-overrider`, which declares it behind this very
// guard. Standalone there is no host, and an untranslated `__()` returns its key.
if (! function_exists('__')) {
    function __(string $key, array|string $replace = [], ?string $locale = null): string
    {
        return $key;
    }
}
