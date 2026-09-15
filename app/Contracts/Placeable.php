<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

interface Placeable
{
    public string $name { get; }

    public string $label { get; }
}
