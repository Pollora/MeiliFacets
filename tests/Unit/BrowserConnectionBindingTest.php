<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Providers\SearchServiceProvider;
use Modules\MeiliFacets\Search\BrowserConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BrowserConnectionBindingTest extends TestCase
{
    #[Test]
    public function it_hands_out_the_connection_without_reading_where_it_points(): void
    {
        $container = new Container;
        new SearchServiceProvider($container)->register();

        $connection = $container->make(BrowserConnection::class);

        $this->assertTrue(new ReflectionClass(BrowserConnection::class)->isUninitializedLazyObject($connection));
    }
}
