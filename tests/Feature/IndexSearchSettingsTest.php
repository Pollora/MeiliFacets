<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Enums\IndexSetting;
use Modules\MeiliFacets\Enums\TypoToleranceSetting;
use Modules\MeiliFacets\Indexing\DefaultSearchableAttributes;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use Modules\MeiliFacets\Indexing\IndexedTaxonomies;
use Modules\MeiliFacets\Indexing\MeiliScoutBridge;
use Modules\MeiliFacets\Indexing\WooCommerceIndexAttributes;
use Modules\MeiliFacets\Search\EngineLimits;
use PHPUnit\Framework\Attributes\Test;
use Pollora\MeiliScout\Indexables\PostIndexable;
use Tests\TestCase;
use WooCommerce;

/** Runs here rather than standalone: the label fields follow the taxonomies WordPress registered. */
final class IndexSearchSettingsTest extends TestCase
{
    private const int REACHABLE_HITS = 1000;

    private const array PRODUCT_FIELDS = ['labels.product_brand', 'labels.product_cat', 'metas._sku'];

    /** Everything MeiliScout copies from `wp_posts`, and what the module adds for display. */
    private const array NEVER_SEARCHED = [
        '*', 'url', 'guid', 'post_content', 'post_excerpt', 'post_name', 'card', 'card.title', 'card.url',
        'terms', 'terms.name', 'metas', 'metas._edit_lock', 'facets', 'price',
    ];

    #[Test]
    public function it_ranks_the_title_then_the_product_then_every_other_label_then_the_prose_by_default(): void
    {
        $this->requireWooCommerce();

        $labels = array_map(
            static fn (string $taxonomy): string => 'labels.'.$taxonomy,
            array_values(array_filter(new IndexedTaxonomies()->all(), is_taxonomy_viewable(...)))
        );
        $expected = array_values(array_unique(['post_title', ...self::PRODUCT_FIELDS, ...$labels, 'excerpt', 'content']));

        $this->assertSame($expected, $this->pushedSearchable());
    }

    #[Test]
    public function it_ranks_no_product_field_without_woocommerce(): void
    {
        $searchable = new DefaultSearchableAttributes(static fn (): array => ['category'], static fn (): bool => false);

        $this->assertSame(['post_title', 'labels.category', 'excerpt', 'content'], $searchable->all());
    }

    #[Test]
    public function it_pushes_the_order_a_project_binds_as_it_is(): void
    {
        $projectOrder = ['post_title', 'excerpt', 'labels.category', 'content'];
        $this->app->instance(SearchableAttributes::class, new readonly class($projectOrder) implements SearchableAttributes
        {
            /**
             * @param  list<string>  $fields
             */
            public function __construct(private array $fields) {}

            /**
             * @return list<string>
             */
            public function all(): array
            {
                return $this->fields;
            }
        });

        try {
            $this->assertSame($projectOrder, $this->pushedSearchable());
        } finally {
            $this->app->forgetInstance(SearchableAttributes::class);
        }
    }

    /** R-27: a field left out cannot be targeted, not even by a query forged with the public key. */
    #[Test]
    public function it_searches_no_technical_field_nor_any_address(): void
    {
        $searchable = $this->pushedSearchable();

        foreach (self::NEVER_SEARCHED as $field) {
            $this->assertNotContains($field, $searchable);
        }
    }

    /** `product_visibility` files `exclude-from-search` and `featured`, `product_type` files `simple`. */
    #[Test]
    public function it_leaves_the_taxonomies_wordpress_does_not_show_out(): void
    {
        $this->requireWooCommerce();
        $searchable = $this->pushedSearchable();

        $this->assertNotContains('labels.product_visibility', $searchable);
        $this->assertNotContains('labels.product_type', $searchable);
    }

    #[Test]
    public function it_turns_typo_tolerance_off_on_what_a_plugin_matches_exactly(): void
    {
        $this->assertSame(['metas._sku'], $this->exactlyMatchedWith(new WooCommerceIndexAttributes));
    }

    /** Written empty rather than left out: a field the engine kept from a former push would stay exact. */
    #[Test]
    public function it_writes_an_empty_exception_list_without_a_plugin(): void
    {
        $this->assertSame([], $this->exactlyMatchedWith(new EmptyIndexAttributes));
    }

    /**
     * What the bridge swaps in for MeiliScout's indexable, wired by the container.
     *
     * @return list<string>
     */
    private function pushedSearchable(): array
    {
        $indexable = $this->app->make(MeiliScoutBridge::class)->declareFacetAttributes([new PostIndexable])[0];

        return $indexable->getIndexSettings()[IndexSetting::SearchableAttributes->value];
    }

    /**
     * @return list<string>
     */
    private function exactlyMatchedWith(IndexAttributes $attributes): array
    {
        $settings = new FacetedPostIndexable(
            $attributes,
            $this->app->make(SearchableAttributes::class),
            new IndexedTaxonomies,
            new EngineLimits(self::REACHABLE_HITS)
        )->getIndexSettings();

        return $settings[IndexSetting::TypoTolerance->value][TypoToleranceSetting::DisableOnAttributes->value];
    }

    private function requireWooCommerce(): void
    {
        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('The product taxonomies are WooCommerce\'s.');
        }
    }
}
