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
use Modules\MeiliFacets\Indexing\WooCommerce\WooCommerceIndexAttributes;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\QueryPlan;
use Modules\MeiliFacets\Tests\Feature\Concerns\PinsIndexedPostTypes;
use Modules\MeiliFacets\Tests\Unit\Doubles\ExcerptFirstSearchableAttributes;
use Pollora\MeiliScout\Indexables\PostIndexable;
use Tests\TestCase;
use WooCommerce;

trait IndexSearchSettingsSupport
{
    private const int REACHABLE_HITS = 1000;

    private const array PRODUCT_FIELDS = ['labels.product_brand', 'labels.product_cat', 'metas._sku'];

    private const array TECHNICAL = ['product_visibility', 'product_type', 'product_shipping_class', 'pos_product_visibility'];

    private const string ATTRIBUTE = 'pa_test_volume';

    /** Everything MeiliScout copies from `wp_posts`, and what the module adds for display. */
    private const array NEVER_SEARCHED = [
        '*', 'url', 'guid', 'post_content', 'post_excerpt', 'post_name', 'card', 'card.title', 'card.url',
        'terms', 'terms.name', 'metas', 'metas._edit_lock', 'facets', 'price',
    ];

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

/** Runs here rather than standalone: the label fields follow the taxonomies WordPress registered. */
uses(TestCase::class, PinsIndexedPostTypes::class, IndexSearchSettingsSupport::class);

beforeEach(function (): void {
    $this->pinIndexedPostTypes();
    $this->forgetIndexWiring();
});

afterEach(function (): void {
    $this->forgetIndexWiring();
    $this->unpinIndexedPostTypes();
});

it('ranks the title then the product then every other label then the prose by default', function (): void {
    $this->requireWooCommerce();

    $labels = array_map(
        static fn (string $taxonomy): string => 'labels.'.$taxonomy,
        array_values(array_diff(new IndexedTaxonomies(new IndexedPostTypes)->all(), self::TECHNICAL))
    );
    $expected = array_values(array_unique(['post_title', ...self::PRODUCT_FIELDS, ...$labels, 'excerpt', 'content']));

    expect($this->pushedSearchable())->toBe($expected);
});

/** The example of `configuration.md`, bound the way it tells a project to. */
it('pushes what a decorator of the default moves', function (): void {
    $default = $this->pushedSearchable();
    $this->app->scoped(SearchableAttributes::class, ExcerptFirstSearchableAttributes::class);
    $this->forgetIndexWiring();

    try {
        $moved = $this->pushedSearchable();
    } finally {
        $this->app->scoped(SearchableAttributes::class, DefaultSearchableAttributes::class);
    }

    expect(array_slice($moved, 0, 2))->toBe(['post_title', 'excerpt']);
    expect(array_values(array_diff($moved, ['excerpt'])))->toBe(array_values(array_diff($default, ['excerpt'])));
});

it('pushes the order a project binds as it is', function (): void {
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
        expect($this->pushedSearchable())->toBe($projectOrder);
    } finally {
        $this->forgetIndexWiring();
    }
});

/** R-27: a field left out cannot be targeted, not even by a query forged with the public key. */
it('searches no technical field nor any address', function (): void {
    $searchable = $this->pushedSearchable();

    foreach (self::NEVER_SEARCHED as $field) {
        $this->assertNotContains($field, $searchable);
    }
});

/** `product_visibility` files `exclude-from-search` and `featured`, `product_type` files `simple`. */
it('leaves the technical taxonomies out', function (): void {
    $this->requireWooCommerce();
    $searchable = $this->pushedSearchable();

    foreach (self::TECHNICAL as $taxonomy) {
        $this->assertNotContains('labels.'.$taxonomy, $searchable);
    }
});

/** A WooCommerce attribute without archives (`pa_*`) is not viewable, yet `100ml` has to be found. */
it('searches the labels of a taxonomy without archives', function (): void {
    register_taxonomy(self::ATTRIBUTE, 'post', ['public' => false]);

    try {
        expect($this->pushedSearchable())->toContain('labels.'.self::ATTRIBUTE);
    } finally {
        unregister_taxonomy(self::ATTRIBUTE);
    }
});

it('turns typo tolerance off on what a plugin matches exactly', function (): void {
    expect($this->exactlyMatchedWith(new WooCommerceIndexAttributes))->toBe(['metas._sku']);
});

/** Written empty rather than left out: a field the engine kept from a former push would stay exact. */
it('writes an empty exception list without a plugin', function (): void {
    expect($this->exactlyMatchedWith(new EmptyIndexAttributes))->toBe([]);
});

it('displays every field the module reads off a hit', function (): void {
    $settings = $this->app->make(FacetedPostIndexable::class, [
        'attributes' => new EmptyIndexAttributes,
        'limits' => new EngineLimits(self::REACHABLE_HITS),
    ])->getIndexSettings();

    $displayed = $settings[IndexSetting::DisplayedAttributes->value];

    expect(array_diff(QueryPlan::VARIANT_RETRIEVED, $displayed))->toBe([]);
    expect(array_diff(QueryPlan::RETRIEVED, $displayed))->toBe([]);
});
