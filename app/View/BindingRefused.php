<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use LogicException;
use Modules\MeiliFacets\Enums\Contract;

final class BindingRefused extends LogicException
{
    public static function attribute(string $name, string $allowed): self
    {
        $reserved = Contract::Attribute->value;

        return new self(
            "Attribute \"{$name}\" cannot be bound to a card field: only {$allowed} can, {$reserved}* excepted. "
            .'Write classes with classes(), and anything else in the view.'
        );
    }

    public static function field(string $field): self
    {
        return new self(
            "Card field \"{$field}\" cannot be bound: a field name is made of letters, digits and underscores only."
        );
    }
}
