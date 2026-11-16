<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\FacetCounter;
use Modules\MeiliFacets\Providers\SearchServiceProvider;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeFacetCounter;
use Tests\TestCase;

// What `Unit\CounterDefaultBindingTest` proves of the container, this proves of the provider itself.
uses(TestCase::class);

it('binds the disjunctive counter of the module', function (): void {
    expect($this->app->make(FacetCounter::class))->toBeInstanceOf(DisjunctiveFacetCounter::class);
});

it('leaves the counter a project bound in place', function (): void {
    $this->app->bind(FacetCounter::class, static fn (): FacetCounter => new FakeFacetCounter);

    new SearchServiceProvider($this->app)->register();

    expect($this->app->make(FacetCounter::class))->toBeInstanceOf(FakeFacetCounter::class);
});
