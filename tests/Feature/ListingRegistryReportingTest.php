<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Exceptions;
use LogicException;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Listing\SearchScope;
use Modules\MeiliFacets\Support\NameRefused;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchScopedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\ThrowingExceptionHandler;
use Tests\TestCase;

trait ListingRegistryReportingHelpers
{
    private ExceptionHandler $hostHandler;

    private function registryWithABrokenBinding(): ListingRegistry
    {
        $container = new Container;
        $container->bind(FakeListing::class, static fn (): never => throw new LogicException('Broken binding.'));
        $registry = new ListingRegistry($container);
        $registry->addDeclaration(FakeListing::class);

        return $registry;
    }
}

/** The host's exception handler is put back after each test. */
uses(TestCase::class, ListingRegistryReportingHelpers::class);

beforeEach(function (): void {
    $this->hostHandler = $this->app->make(ExceptionHandler::class);
});

afterEach(function (): void {
    $this->app->instance(ExceptionHandler::class, $this->hostHandler);
});

it('reports a declared listing whose binding throws', function (): void {
    Exceptions::fake();

    expect($this->registryWithABrokenBinding()->names())->toBe([]);

    Exceptions::assertReported(LogicException::class);
});

it('leaves the listing out when the handler throws too', function (): void {
    $this->app->instance(ExceptionHandler::class, new ThrowingExceptionHandler);

    expect($this->registryWithABrokenBinding()->names())->toBe([]);
});

it('reports a second listing declared under a taken name and keeps the first', function (): void {
    Exceptions::fake();
    $first = FakeListing::named('products');
    $container = new Container;
    $container->instance(FakeListing::class, $first);
    $container->bind(FakeSearchScopedListing::class, static fn (): FakeSearchScopedListing => new FakeSearchScopedListing(FakeListing::named('products'), new SearchScope([])));
    $registry = new ListingRegistry($container);
    $registry->addDeclaration(FakeListing::class);
    $registry->addDeclaration(FakeSearchScopedListing::class);

    expect($registry->onlyOne())->toBe($first);
    Exceptions::assertReported(NameRefused::class);
});
