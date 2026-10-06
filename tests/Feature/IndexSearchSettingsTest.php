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
use Modules\MeiliFacets\Indexing\IndexedPostTypes;
use Modules\MeiliFacets\Indexing\IndexedTaxonomies;
use Modules\MeiliFacets\Indexing\MeiliScoutBridge;
use Modules\MeiliFacets\Indexing\WooCommerceIndexAttributes;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\QueryPlan;
use Modules\MeiliFacets\Tests\Unit\Doubles\ExcerptFirstSearchableAttributes;
use PHPUnit\Framework\Attributes\Test;
use Pollora\MeiliScout\Indexables\PostIndexable;
use Tests\TestCase;
use WooCommerce;

/** Runs here rather than standalone: the label fields follow the taxonomies WordPress registered. */
final class IndexSearchSettingsTest extends TestCase
{
    use PinsIndexedPostTypes;

    private const int REACHABLE_HITS = 1000;

    private const array PRODUCT_FIELDS = ['labels.product_brand', 'labels.product_cat', 'metas._sku'];

    private const array TECHNICAL = ['product_visibility', 'product_type', 'product_shipping_class', 'pos_product_visibility'];

    private const string ATTRIBUTE = 'pa_test_volume';

    /** Everything MeiliScout copies from `wp_posts`, and what the module adds for display. */
    private const array NEVER_SEARCHED = [
        '*', 'url', 'guid', 'post_content', 'post_excerpt', 'post_name', 'card', 'card.title', 'card.url',
        'terms', 'terms.name', 'metas', 'metas._edit_lock', 'facets', 'price',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinIndexedPostTypes();
        $this->forgetIndexWiring();
    }

    protected function tearDown(): void
    {
        $this->forgetIndexWiring();
        $this->unpinIndexedPostTypes();

        parent::tearDown();
    }

    #[Test]
    public function it_ranks_the_title_then_the_product_then_every_other_label_then_the_prose_by_default(): void
    {
        $this->requireWooCommerce();

        $labels = array_map(
            static fn (string $taxonomy): string => 'labels.'.$taxonomy,
            array_values(array_diff(new IndexedTaxonomies(new IndexedPostTypes)->all(), self::TECHNICAL))
        );
        $expected = array_values(array_unique(['post_title', ...self::PRODUCT_FIELDS, ...$labels, 'excerpt', 'content']));

        $this->assertSame($expected, $this->pushedSearchable());
    }

    /** The example of `configuration.md`, bound the way it tells a project to. */
    #[Test]
    public function it_pushes_what_a_decorator_of_the_default_moves(): void
    {
        $default = $this->pushedSearchable();
        $this->app->scoped(SearchableAttributes::class, ExcerptFirstSearchableAttributes::class);
        $this->forgetIndexWiring();

        try {
            $moved = $this->pushedSearchable();
        } finally {
            $this->app->scoped(SearchableAttributes::class, DefaultSearchableAttributes::class);
        }

        $this->assertSame(['post_title', 'excerpt'], array_slice($moved, 0, 2));
        $this->assertSame(array_values(array_diff($default, ['excerpt'])), array_values(array_diff($moved, ['excerpt'])));
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
            $this->forgetIndexWiring();
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
    public function it_leaves_the_technical_taxonomies_out(): void
    {
        $this->requireWooCommerce();
        $searchable = $this->pushedSearchable();

        foreach (self::TECHNICAL as $taxonomy) {
            $this->assertNotContains('labels.'.$taxonomy, $searchable);
        }
    }

    /** A WooCommerce attribute without archives (`pa_*`) is not viewable, yet `100ml` has to be found. */
    #[Test]
    public function it_searches_the_labels_of_a_taxonomy_without_archives(): void
    {
        register_taxonomy(self::ATTRIBUTE, 'post', ['public' => false]);

        try {
            $this->assertContains('labels.'.self::ATTRIBUTE, $this->pushedSearchable());
        } finally {
            unregister_taxonomy(self::ATTRIBUTE);
        }
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

    #[Test]
    public function it_displays_every_field_the_module_reads_off_a_hit(): void
    {
        $settings = $this->app->make(FacetedPostIndexable::class, [
            'attributes' => new EmptyIndexAttributes,
            'limits' => new EngineLimits(self::REACHABLE_HITS),
        ])->getIndexSettings();

        $displayed = $settings[IndexSetting::DisplayedAttributes->value];

        $this->assertSame([], array_diff(QueryPlan::VARIANT_RETRIEVED, $displayed));
        $this->assertSame([], array_diff(QueryPlan::RETRIEVED, $displayed));
    }

    /** All three are scoped: kept, they would carry the taxonomies and the order of a former test. */
    private function forgetIndexWiring(): void
    {
        $this->app->forgetInstance(SearchableAttributes::class);
        $this->app->forgetInstance(IndexedTaxonomies::class);
        $this->app->forgetInstance(FacetedPostIndexable::class);
    }

    /**
     * @return list<string>
     */
    private function exactlyMatchedWith(IndexAttributes $attributes): array
    {
        $settings = $this->app->make(FacetedPostIndexable::class, [
            'attributes' => $attributes,
            'limits' => new EngineLimits(self::REACHABLE_HITS),
        ])->getIndexSettings();

        return $settings[IndexSetting::TypoTolerance->value][TypoToleranceSetting::DisableOnAttributes->value];
    }

    private function requireWooCommerce(): void
    {
        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('The product taxonomies are WooCommerce\'s.');
        }
    }
}
