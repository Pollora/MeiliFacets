<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\DocumentAssembly;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use Modules\MeiliFacets\Indexing\IndexedPostTypes;
use Modules\MeiliFacets\Indexing\IndexedTaxonomies;
use Modules\MeiliFacets\Indexing\MeiliScoutBridge;
use Modules\MeiliFacets\Indexing\PostDocument;
use Modules\MeiliFacets\Indexing\PostText;
use Modules\MeiliFacets\Indexing\TermAncestry;
use Modules\MeiliFacets\Indexing\WooCommerce\AnonymousVisitor;
use Modules\MeiliFacets\Indexing\WooCommerce\EmptyVariantFields;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductPopularity;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductPriceProjector;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductStock;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductVariants;
use Modules\MeiliFacets\Indexing\WooCommerce\ShopFields;
use Modules\MeiliFacets\Indexing\WooCommerce\ShopTaxLocation;
use Modules\MeiliFacets\Indexing\WooCommerce\VariantDocuments;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use Pollora\MeiliScout\Contracts\Indexable;
use Pollora\MeiliScout\Indexables\PostIndexable;
use WP_Post;

beforeEach(function (): void {
    WordPress::boot();
    WordPress::bootWooCommerce();
    WordPress::fake('get_option', ['post']);
    // `product_type` is an internal taxonomy: filtered on, never searched by its labels.
    WordPress::fake('get_object_taxonomies', ['category', 'product_type']);
    WordPress::fake('strip_shortcodes', static fn (string $markup): string => $markup);
    WordPress::fake('get_ancestors', []);
    WordPress::fake('wc_get_product', false);
    WordPress::fake('get_current_user_id', 0);
    WordPress::fake('wp_set_current_user', null);
    WordPress::fake('WC', (object) ['countries' => new class
    {
        /** @param  array<int, mixed>  $arguments */
        public function __call(string $name, array $arguments): string
        {
            return '';
        }
    }]);
});

function postDocument(): PostDocument
{
    $card = new class implements CardProjector
    {
        public function project(WP_Post $post): array
        {
            return ['title' => 'T'];
        }
    };
    $hierarchy = new WordPressTermHierarchy;

    return new PostDocument(new TermAncestry($hierarchy), new IndexedTaxonomies(new IndexedPostTypes), new PostText, new ShopFields(
        $card,
        new ProductVariants(new EmptyVariantFields, $hierarchy, 'medium'),
        new ProductStock,
        new ProductPopularity,
        new ProductPriceProjector,
        new ShopTaxLocation,
        new AnonymousVisitor,
    ));
}

/** @return array<string, mixed> */
function term(int $id, string $slug, string $taxonomy): array
{
    return ['term_id' => $id, 'name' => ucfirst($slug), 'slug' => $slug, 'taxonomy' => $taxonomy, 'term_taxonomy_id' => $id, 'parent' => 0];
}

it('adds the facets, the labels, the text and the card to what MeiliScout built, keeping it', function (): void {
    $document = ['ID' => 7, 'terms' => [term(1, 'variable', 'product_type'), term(2, 'news', 'category')]];
    $post = new WP_Post(['post_content' => '<p>Body</p>', 'post_excerpt' => 'Lead']);

    $completed = new MeiliScoutBridge(postDocument(), indexable())->addModuleFields($document, $post);

    expect($completed)->toMatchArray([
        'ID' => 7,
        'terms' => $document['terms'],
        'facets' => ['product_type' => ['variable'], 'category' => ['news']],
        'labels' => ['category' => ['News']],
        'excerpt' => 'Lead',
        'content' => 'Body',
        'card' => ['title' => 'T'],
    ]);
});

it('puts the facet declaring index in place of MeiliScout\'s post index, and only of it', function (): void {
    $faceted = indexable();
    $post = new PostIndexable;
    $other = $this->createStub(Indexable::class);

    $declared = new MeiliScoutBridge(postDocument(), $faceted)->declareFacetAttributes([$post, $other, $faceted]);

    expect($declared)->toBe([$faceted, $other, $faceted]);
});

it('ranks by relevance before the sort the visitor chose', function (): void {
    $rules = new MeiliScoutBridge(postDocument(), indexable())->rankByRelevanceFirst();

    expect($rules)->toContain('words', 'sort')
        ->and(array_search('words', $rules, true))->toBeLessThan(array_search('sort', $rules, true));
});

function indexable(): FacetedPostIndexable
{
    $attributes = new class implements IndexAttributes
    {
        public function exactlyMatched(): array
        {
            return ['sku'];
        }

        public function filterable(): array
        {
            return ['price'];
        }

        public function sortable(): array
        {
            return ['price'];
        }

        public function displayed(): array
        {
            return [];
        }
    };
    $searchable = new class implements SearchableAttributes
    {
        public function all(): array
        {
            return ['title'];
        }
    };

    return new FacetedPostIndexable(
        $attributes,
        $searchable,
        new IndexedTaxonomies(new IndexedPostTypes),
        new EngineLimits(1000, 100),
        new VariantDocuments(new EmptyVariantFields),
    );
}

it('keeps what MeiliScout declares in the index settings, adding to it', function (): void {
    WordPress::fake('get_option', static fn (string $key, mixed $default = null): mixed => $default);
    $base = new PostIndexable()->getIndexSettings();

    $settings = indexable()->getIndexSettings();

    expect($settings['filterableAttributes'])->toContain(...$base['filterableAttributes'])
        ->and($settings['sortableAttributes'])->toContain(...$base['sortableAttributes']);

    foreach (['faceting', 'pagination', 'typoTolerance'] as $setting) {
        expect(array_intersect_key($settings[$setting], $base[$setting] ?? []))
            ->toHaveCount(count($base[$setting] ?? []));
    }
});

it('filters the index on the facets, the variants and what the project declares', function (): void {
    WordPress::fake('get_option', static fn (string $key, mixed $default = null): mixed => $key === 'meiliscout/indexed_post_types' ? ['post'] : $default);

    expect(indexable()->getIndexSettings()['filterableAttributes'])->toContain('facets.category', 'parent_id', 'price');
});

it('indexes a document per variant of a product', function (): void {
    $product = ['ID' => 7, 'card' => ['variants' => [['facets' => [], 'price' => 5.0]]]];

    expect(array_column(indexable()->dependentDocuments($product, null), 'ID'))->toBe(['7-0']);
});
