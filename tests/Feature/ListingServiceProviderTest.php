<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Illuminate\Container\Container;
use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Contracts\TermLabels;
use Modules\MeiliFacets\Contracts\TermScope;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use Modules\MeiliFacets\Listing\WooCommerceSorts;
use Modules\MeiliFacets\Listing\WordPressTermLabels;
use Modules\MeiliFacets\Listing\WordPressTermScope;
use Modules\MeiliFacets\Providers\ListingServiceProvider;
use Modules\MeiliFacets\Tests\Feature\Concerns\RegistersAProvider;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermHierarchy;
use Tests\TestCase;

trait ListingServiceProviderSteps
{
    /** Bound by `IndexingServiceProvider`, which this container leaves out. */
    private function withTheHierarchyItReads(): Closure
    {
        return static fn (Container $container) => $container->instance(TermHierarchy::class, new FakeTermHierarchy);
    }

    private function projectFacets(): ProductFacets
    {
        return new readonly class implements ProductFacets
        {
            public function all(): array
            {
                return [];
            }
        };
    }

    private function projectSorts(): ProductSorts
    {
        return new readonly class implements ProductSorts
        {
            public function all(): array
            {
                return [];
            }
        };
    }
}

uses(TestCase::class, RegistersAProvider::class, ListingServiceProviderSteps::class);

it('reads term labels from wordpress', function (): void {
    $container = $this->registered(ListingServiceProvider::class);

    expect($container->make(TermLabels::class))->toBeInstanceOf(WordPressTermLabels::class);
});

// The scope remembers the queried term: it must not outlive the request that read it.
it('scopes terms from wordpress once per request', function (): void {
    $container = $this->registered(ListingServiceProvider::class, $this->withTheHierarchyItReads());
    $scope = $container->make(TermScope::class);

    expect($scope)->toBeInstanceOf(WordPressTermScope::class);
    expect($container->make(TermScope::class))->toBe($scope);

    $container->forgetScopedInstances();

    expect($container->make(TermScope::class))->not->toBe($scope);
});

it('offers the woocommerce facets and sorts when the project binds none', function (): void {
    $container = $this->registered(ListingServiceProvider::class);

    expect($container->make(ProductFacets::class))->toBeInstanceOf(WooCommerceFacets::class);
    expect($container->make(ProductSorts::class))->toBeInstanceOf(WooCommerceSorts::class);
});

it('keeps the facets and sorts a project bound first', function (): void {
    $facets = $this->projectFacets();
    $sorts = $this->projectSorts();
    $container = $this->registered(
        ListingServiceProvider::class,
        static fn (Container $project) => $project->instance(ProductFacets::class, $facets),
        static fn (Container $project) => $project->instance(ProductSorts::class, $sorts),
    );

    expect($container->make(ProductFacets::class))->toBe($facets);
    expect($container->make(ProductSorts::class))->toBe($sorts);
});
