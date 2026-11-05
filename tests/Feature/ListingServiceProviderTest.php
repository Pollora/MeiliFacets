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
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermHierarchy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ListingServiceProviderTest extends TestCase
{
    use RegistersAProvider;

    #[Test]
    public function it_reads_term_labels_from_wordpress(): void
    {
        $container = $this->registered(ListingServiceProvider::class);

        $this->assertInstanceOf(WordPressTermLabels::class, $container->make(TermLabels::class));
    }

    /** The scope remembers the queried term: it must not outlive the request that read it. */
    #[Test]
    public function it_scopes_terms_from_wordpress_once_per_request(): void
    {
        $container = $this->registered(ListingServiceProvider::class, $this->withTheHierarchyItReads());
        $scope = $container->make(TermScope::class);

        $this->assertInstanceOf(WordPressTermScope::class, $scope);
        $this->assertSame($scope, $container->make(TermScope::class));

        $container->forgetScopedInstances();

        $this->assertNotSame($scope, $container->make(TermScope::class));
    }

    #[Test]
    public function it_offers_the_woocommerce_facets_and_sorts_when_the_project_binds_none(): void
    {
        $container = $this->registered(ListingServiceProvider::class);

        $this->assertInstanceOf(WooCommerceFacets::class, $container->make(ProductFacets::class));
        $this->assertInstanceOf(WooCommerceSorts::class, $container->make(ProductSorts::class));
    }

    #[Test]
    public function it_keeps_the_facets_and_sorts_a_project_bound_first(): void
    {
        $facets = $this->projectFacets();
        $sorts = $this->projectSorts();
        $container = $this->registered(
            ListingServiceProvider::class,
            static fn (Container $project) => $project->instance(ProductFacets::class, $facets),
            static fn (Container $project) => $project->instance(ProductSorts::class, $sorts),
        );

        $this->assertSame($facets, $container->make(ProductFacets::class));
        $this->assertSame($sorts, $container->make(ProductSorts::class));
    }

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
