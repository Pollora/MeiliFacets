<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Contracts\FacetCounter;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeFacetCounter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** On a container of its own: the application's own bindings must not decide the outcome. */
final class CounterSeamBindingTest extends TestCase
{
    #[Test]
    public function it_binds_its_default_when_the_project_says_nothing(): void
    {
        $container = new Container;

        $container->bindIf(FacetCounter::class, DisjunctiveFacetCounter::class);

        $this->assertInstanceOf(DisjunctiveFacetCounter::class, $container->make(FacetCounter::class));
    }

    #[Test]
    public function it_steps_aside_for_a_project_that_bound_first(): void
    {
        $container = new Container;

        $container->bind(FacetCounter::class, $this->projectCounter());
        $container->bindIf(FacetCounter::class, DisjunctiveFacetCounter::class);

        $this->assertNotInstanceOf(DisjunctiveFacetCounter::class, $container->make(FacetCounter::class));
    }

    /** The path a project actually takes: its own provider registers after the module's. */
    #[Test]
    public function it_gives_way_to_a_project_that_binds_after_it(): void
    {
        $container = new Container;

        $container->bindIf(FacetCounter::class, DisjunctiveFacetCounter::class);
        $container->bind(FacetCounter::class, $this->projectCounter());

        $this->assertNotInstanceOf(DisjunctiveFacetCounter::class, $container->make(FacetCounter::class));
    }

    private function projectCounter(): callable
    {
        return static fn (): FacetCounter => new FakeFacetCounter;
    }
}
