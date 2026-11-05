<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

use LogicException;

final class NameRefused extends LogicException
{
    public static function unknown(string $kind, string $name, string $listed): self
    {
        return new self("No {$kind} named \"{$name}\". Declared: {$listed}.");
    }

    public static function unnamed(string $kind, string $declared): self
    {
        return new self("Name the {$kind}: {$declared}.");
    }
}
