<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\BrowserConnectionBinding;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Providers\SearchServiceProvider;
use Modules\MeiliFacets\Search\BrowserConnection;
use ReflectionClass;

it('hands out the connection without reading where it points', function (): void {
    $container = new Container;
    new SearchServiceProvider($container)->register();

    $connection = $container->make(BrowserConnection::class);

    expect(new ReflectionClass(BrowserConnection::class)->isUninitializedLazyObject($connection))->toBeTrue();
});
