<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\CounterDefaultBinding;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Contracts\FacetCounter;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeFacetCounter;

// On a container of its own: the application's own bindings must not decide the outcome.

function projectCounter(): callable
{
    return static fn (): FacetCounter => new FakeFacetCounter;
}

it('binds its default when the project says nothing', function (): void {
    $container = new Container;

    $container->bindIf(FacetCounter::class, DisjunctiveFacetCounter::class);

    expect($container->make(FacetCounter::class))->toBeInstanceOf(DisjunctiveFacetCounter::class);
});

it('steps aside for a project that bound first', function (): void {
    $container = new Container;

    $container->bind(FacetCounter::class, projectCounter());
    $container->bindIf(FacetCounter::class, DisjunctiveFacetCounter::class);

    expect($container->make(FacetCounter::class))->not->toBeInstanceOf(DisjunctiveFacetCounter::class);
});

/** The path a project actually takes: its own provider registers after the module's. */
it('gives way to a project that binds after it', function (): void {
    $container = new Container;

    $container->bindIf(FacetCounter::class, DisjunctiveFacetCounter::class);
    $container->bind(FacetCounter::class, projectCounter());

    expect($container->make(FacetCounter::class))->not->toBeInstanceOf(DisjunctiveFacetCounter::class);
});
