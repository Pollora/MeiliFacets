<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** On a container of its own: the application's own bindings must not decide the outcome. */
final class ProductSeamBindingTest extends TestCase
{
    #[Test]
    public function it_binds_its_default_when_the_project_says_nothing(): void
    {
        $container = new Container;

        $container->scopedIf(ProductFacets::class, WooCommerceFacets::class);

        $this->assertInstanceOf(WooCommerceFacets::class, $container->make(ProductFacets::class));
    }

    #[Test]
    public function it_steps_aside_for_a_project_that_bound_first(): void
    {
        $container = new Container;

        $container->scoped(ProductFacets::class, $this->projectFacets());
        $container->scopedIf(ProductFacets::class, WooCommerceFacets::class);

        $this->assertNotInstanceOf(WooCommerceFacets::class, $container->make(ProductFacets::class));
    }

    #[Test]
    public function it_gives_way_to_a_project_that_binds_after_it(): void
    {
        $container = new Container;

        $container->scopedIf(ProductFacets::class, WooCommerceFacets::class);
        $container->scoped(ProductFacets::class, $this->projectFacets());

        $this->assertNotInstanceOf(WooCommerceFacets::class, $container->make(ProductFacets::class));
    }

    #[Test]
    public function it_hands_the_same_instance_out_twice(): void
    {
        $container = new Container;

        $container->scopedIf(ProductFacets::class, WooCommerceFacets::class);

        $this->assertSame($container->make(ProductFacets::class), $container->make(ProductFacets::class));
    }

    private function projectFacets(): callable
    {
        return static fn (): ProductFacets => new class implements ProductFacets
        {
            public function all(): array
            {
                return [new Facet('pa_couleur', 'Colour')];
            }
        };
    }
}
