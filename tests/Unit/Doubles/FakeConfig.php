<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

/** What `#[Config(...)]` and `config()` read: the values a test set on `WordPress::$config`. */
final class FakeConfig
{
    public function get(string $key, mixed $default = null): mixed
    {
        return WordPress::$config[$key] ?? $default;
    }
}
