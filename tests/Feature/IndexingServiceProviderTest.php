<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Contracts\VariantFields;
use Modules\MeiliFacets\Indexing\DefaultSearchableAttributes;
use Modules\MeiliFacets\Indexing\DeferredCardProjector;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use Modules\MeiliFacets\Providers\IndexingServiceProvider;
use Modules\MeiliFacets\Tests\Feature\Concerns\RegistersAProvider;
use Modules\MeiliFacets\Tests\Unit\Doubles\ExcerptFirstSearchableAttributes;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WC_Product_Variation;
use WooCommerce;
use WP_Post;

final class IndexingServiceProviderTest extends TestCase
{
    use RegistersAProvider;

    #[Test]
    public function it_reads_the_term_hierarchy_from_wordpress(): void
    {
        $container = $this->registered(IndexingServiceProvider::class);

        $this->assertInstanceOf(WordPressTermHierarchy::class, $container->make(TermHierarchy::class));
    }

    #[Test]
    public function it_projects_cards_through_its_own_default_when_the_project_binds_none(): void
    {
        $container = $this->registered(IndexingServiceProvider::class);

        $this->assertInstanceOf(DeferredCardProjector::class, $container->make(CardProjector::class));
    }

    #[Test]
    public function it_keeps_the_card_projector_a_project_bound_first(): void
    {
        $card = $this->projectCard();
        $container = $this->registered(
            IndexingServiceProvider::class,
            static fn (Container $project) => $project->instance(CardProjector::class, $card)
        );

        $this->assertSame($card, $container->make(CardProjector::class));
    }

    #[Test]
    public function it_adds_no_field_to_a_variant_when_the_project_binds_none(): void
    {
        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('Variants are WooCommerce\'s.');
        }

        $fields = $this->registered(IndexingServiceProvider::class)->make(VariantFields::class);

        $this->assertSame([], $fields->project(new WC_Product_Variation));
    }

    #[Test]
    public function it_keeps_the_variant_fields_a_project_bound_first(): void
    {
        $fields = $this->projectVariantFields();
        $container = $this->registered(
            IndexingServiceProvider::class,
            static fn (Container $project) => $project->instance(VariantFields::class, $fields)
        );

        $this->assertSame($fields, $container->make(VariantFields::class));
    }

    #[Test]
    public function it_orders_the_searched_fields_once_per_request_when_the_project_binds_none(): void
    {
        $container = $this->registered(IndexingServiceProvider::class);
        $order = $container->make(SearchableAttributes::class);

        $this->assertInstanceOf(DefaultSearchableAttributes::class, $order);
        $this->assertSame($order, $container->make(SearchableAttributes::class));
    }

    #[Test]
    public function it_keeps_the_search_order_a_project_bound_first(): void
    {
        $container = $this->registered(
            IndexingServiceProvider::class,
            static fn (Container $project) => $project->scoped(SearchableAttributes::class, ExcerptFirstSearchableAttributes::class)
        );

        $this->assertInstanceOf(ExcerptFirstSearchableAttributes::class, $container->make(SearchableAttributes::class));
    }

    private function projectCard(): CardProjector
    {
        return new readonly class implements CardProjector
        {
            public function project(WP_Post $post): array
            {
                return [];
            }
        };
    }

    private function projectVariantFields(): VariantFields
    {
        return new readonly class implements VariantFields
        {
            public function project(WC_Product_Variation $variation): array
            {
                return [];
            }
        };
    }
}
