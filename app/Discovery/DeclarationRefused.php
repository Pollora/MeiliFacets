<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Discovery;

use InvalidArgumentException;

final class DeclarationRefused extends InvalidArgumentException
{
    public static function notAListing(string $class): self
    {
        return new self("\"{$class}\" is declared as a listing but does not implement the Listing contract.");
    }
}
