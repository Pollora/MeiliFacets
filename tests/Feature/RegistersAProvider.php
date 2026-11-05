<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;

/** A container of its own per test: the host's bindings decide nothing, and nothing is left behind in it. */
trait RegistersAProvider
{
    /**
     * Each project binding registers first, the way a project provider listed before the module's would.
     *
     * @param  class-string<ServiceProvider>  $provider
     * @param  Closure(Container): void  ...$projectBindings
     */
    private function registered(string $provider, Closure ...$projectBindings): Container
    {
        $container = new Container;

        foreach ($projectBindings as $bind) {
            $bind($container);
        }

        new $provider($container)->register();

        return $container;
    }
}
