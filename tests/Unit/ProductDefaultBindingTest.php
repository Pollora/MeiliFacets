<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\WooCommerceFacets;

// On a container of its own: the application's own bindings must not decide the outcome.

function productDefaultBindingProjectFacets(): callable
{
    return static fn (): ProductFacets => new class implements ProductFacets
    {
        public function all(): array
        {
            return [new Facet('pa_couleur', 'Colour')];
        }
    };
}

it('binds its default when the project says nothing', function (): void {
    $container = new Container;

    $container->scopedIf(ProductFacets::class, WooCommerceFacets::class);

    expect($container->make(ProductFacets::class))->toBeInstanceOf(WooCommerceFacets::class);
});

it('steps aside for a project that bound first', function (): void {
    $container = new Container;

    $container->scoped(ProductFacets::class, productDefaultBindingProjectFacets());
    $container->scopedIf(ProductFacets::class, WooCommerceFacets::class);

    expect($container->make(ProductFacets::class))->not->toBeInstanceOf(WooCommerceFacets::class);
});

it('gives way to a project that binds after it', function (): void {
    $container = new Container;

    $container->scopedIf(ProductFacets::class, WooCommerceFacets::class);
    $container->scoped(ProductFacets::class, productDefaultBindingProjectFacets());

    expect($container->make(ProductFacets::class))->not->toBeInstanceOf(WooCommerceFacets::class);
});

it('hands the same instance out twice', function (): void {
    $container = new Container;

    $container->scopedIf(ProductFacets::class, WooCommerceFacets::class);

    expect($container->make(ProductFacets::class))->toBe($container->make(ProductFacets::class));
});
