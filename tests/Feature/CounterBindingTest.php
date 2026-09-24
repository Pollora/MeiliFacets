<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\FacetCounter;
use Modules\MeiliFacets\Providers\SearchServiceProvider;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeFacetCounter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** What `Unit\CounterSeamBindingTest` proves of the container, this proves of the provider itself. */
final class CounterBindingTest extends TestCase
{
    #[Test]
    public function it_binds_the_disjunctive_counter_of_the_module(): void
    {
        $this->assertInstanceOf(DisjunctiveFacetCounter::class, $this->app->make(FacetCounter::class));
    }

    #[Test]
    public function it_leaves_the_counter_a_project_bound_in_place(): void
    {
        $this->app->bind(FacetCounter::class, static fn (): FacetCounter => new FakeFacetCounter);

        new SearchServiceProvider($this->app)->register();

        $this->assertInstanceOf(FakeFacetCounter::class, $this->app->make(FacetCounter::class));
    }
}
