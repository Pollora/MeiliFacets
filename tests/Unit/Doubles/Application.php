<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Illuminate\Container\Container;

/** The slice of the Laravel application the module reads outside the container. */
final class Application extends Container
{
    public string $locale = 'en';

    public function __construct()
    {
        $this->instance('config', new FakeConfig);
    }

    public function runningUnitTests(): bool
    {
        return true;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
