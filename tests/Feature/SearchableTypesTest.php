<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Indexing\IndexedPostTypes;
use Modules\MeiliFacets\Indexing\WooCommerce\WooCommerceProductFields;
use Modules\MeiliFacets\Listing\NoFieldToSearch;
use Modules\MeiliFacets\Listing\SearchableType;
use Modules\MeiliFacets\SiteSearch\AcceptedSearchTypes;
use Modules\MeiliFacets\SiteSearch\AttributesToSearchOn;
use Modules\MeiliFacets\SiteSearch\SearchablePostTypes;
use Modules\MeiliFacets\SiteSearch\SearchableTypeFactory;
use Modules\MeiliFacets\SiteSearch\SearchRoot;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\SiteSearch\SearchTypeRefused;
use Modules\MeiliFacets\SiteSearch\UnsearchableFields;
use Modules\MeiliFacets\SiteSearch\WooCommerceSearchableTypes;
use Modules\MeiliFacets\SiteSearch\WordPressSearchableTypes;
use Modules\MeiliFacets\Tests\Feature\Concerns\PinsIndexedPostTypes;
use Modules\MeiliFacets\Tests\Unit\Doubles\RetitledSearchableTypes;
use Tests\TestCase;
use WooCommerce;

trait SearchableTypesSteps
{
    private const string NOTE = 'meili_test_note';

    private const string HIDDEN = 'meili_test_hidden';

    /** Resolved once per request by the container: a binding changed in a test needs them forgotten. */
    private const array WIRING = [
        SearchableTypes::class,
        WordPressSearchableTypes::class,
        WooCommerceSearchableTypes::class,
        AcceptedSearchTypes::class,
    ];

    private function withWooCommerce(): WooCommerceSearchableTypes
    {
        return $this->declared(static fn (): bool => true);
    }

    private function withoutWooCommerce(): WooCommerceSearchableTypes
    {
        return $this->declared(static fn (): bool => false);
    }

    /**
     * @param  Closure(): bool  $pluginIsActive
     */
    private function declared(Closure $pluginIsActive): WooCommerceSearchableTypes
    {
        $factory = $this->factory();
        $postTypes = new SearchablePostTypes(new IndexedPostTypes);

        return new WooCommerceSearchableTypes(
            new WordPressSearchableTypes($postTypes, $factory),
            $postTypes,
            $factory,
            new WooCommerceProductFields($pluginIsActive),
            $pluginIsActive,
        );
    }

    private function factory(?SearchableAttributes $order = null): SearchableTypeFactory
    {
        $order ??= $this->app->make(SearchableAttributes::class);

        return new SearchableTypeFactory(new AttributesToSearchOn($order));
    }

    /**
     * @param  list<string>  $fields
     */
    private function order(array $fields): SearchableAttributes
    {
        return new readonly class($fields) implements SearchableAttributes
        {
            /**
             * @param  list<string>  $fields
             */
            public function __construct(private array $fields) {}

            public function all(): array
            {
                return $this->fields;
            }
        };
    }

    private function accepted(SearchableTypes $declared): AcceptedSearchTypes
    {
        return new AcceptedSearchTypes(
            $declared,
            new IndexedPostTypes,
            new AttributesToSearchOn($this->app->make(SearchableAttributes::class))
        );
    }

    private function rootOf(AcceptedSearchTypes $accepted): SearchRoot
    {
        return new SearchRoot(SearchRoot::DEFAULT_NAME, new SearchSettings, $accepted->all());
    }

    /**
     * @param  list<string>  $postTypes
     */
    private function index(array $postTypes): void
    {
        $this->unpinIndexedPostTypes();
        add_filter(self::INDEXED_POST_TYPES_OPTION, static fn (): array => $postTypes);
    }

    private function forgetWiring(): void
    {
        foreach (self::WIRING as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

    private function requireWooCommerce(): void
    {
        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('Products are declared by WooCommerce.');
        }
    }
}

// Runs here rather than standalone: labels, archives, taxonomies and the indexed types are WordPress's.
uses(TestCase::class, PinsIndexedPostTypes::class, SearchableTypesSteps::class);

beforeEach(function (): void {
    $this->pinIndexedPostTypes();
});

afterEach(function (): void {
    $this->unpinIndexedPostTypes();
    unregister_post_type(self::NOTE);
    unregister_post_type(self::HIDDEN);
});

it('binds the woocommerce declaration when the project says nothing', function (): void {
    expect($this->app->make(SearchableTypes::class))->toBeInstanceOf(WooCommerceSearchableTypes::class);
});

it('declares an indexed searchable post type without code', function (): void {
    register_post_type(self::NOTE, [
        'public' => true,
        'labels' => ['name' => 'Notes', 'all_items' => 'All notes'],
    ]);
    $this->index(['post', self::NOTE]);

    $note = $this->withoutWooCommerce()->all()[self::NOTE];

    expect([$note->heading, $note->seeAllLabel, $note->card, $note->archive])->toBe(['Notes', 'All notes', SearchableTypeFactory::CARD, null]);
    expect($note->baseFilter)->toBe(['post_type = "meili_test_note"', 'post_status = "publish"']);
});

it('leaves out a type hidden from search or left out of the index', function (): void {
    register_post_type(self::HIDDEN, ['public' => true, 'exclude_from_search' => true]);
    $this->index(['post', self::HIDDEN]);

    expect(array_keys($this->withoutWooCommerce()->all()))->toBe(['post']);
});

it('reads the labels and the archive wordpress gives each type', function (): void {
    $this->requireWooCommerce();

    foreach ($this->withWooCommerce()->all() as $postType => $type) {
        $labels = get_post_type_object($postType)->labels;

        expect([$type->heading, $type->seeAllLabel, $type->archive])->toBe([$labels->name, $labels->all_items, get_post_type_archive_link($postType)], $postType);
    }
});

it('leads nowhere for a type without archive', function (): void {
    expect($this->factory()->forPostType('page')->archive)->toBeNull();
});

it('declares products first then the other types in the order they are indexed', function (): void {
    $this->requireWooCommerce();
    register_post_type(self::NOTE, ['public' => true]);
    $this->index([self::NOTE, 'post', 'product']);

    expect(array_keys($this->withWooCommerce()->all()))->toBe(['product', self::NOTE, 'post']);
});

it('declares no product without woocommerce', function (): void {
    expect(array_keys($this->withoutWooCommerce()->all()))->toBe(['post']);
});

it('searches each type within the index search order', function (): void {
    $this->requireWooCommerce();

    $order = $this->app->make(SearchableAttributes::class)->all();

    foreach ($this->withWooCommerce()->all() as $type) {
        expect(array_diff($type->searchOn, $order))->toBe([], $type->postType);
    }
});

it('searches posts by title labels and excerpt and products by what names them', function (): void {
    $this->requireWooCommerce();

    $types = $this->withWooCommerce()->all();
    $postLabels = array_map(
        static fn (string $taxonomy): string => 'labels.'.$taxonomy,
        get_object_taxonomies('post')
    );

    expect($types['product']->searchOn)->toBe(['post_title', 'labels.product_brand', 'labels.product_cat', 'metas._sku']);
    expect($types['post']->searchOn)->toBe(['post_title', ...$postLabels, 'excerpt']);
});

it('follows an order the project narrowed', function (): void {
    $type = $this->factory($this->order(['post_title', 'content']))->forPostType('post');

    expect($type->searchOn)->toBe(['post_title']);
});

it('refuses a type the order leaves nothing to search', function (): void {
    $this->expectException(NoFieldToSearch::class);
    $this->expectExceptionMessage('Post type "post" has no field to search');

    $this->factory($this->order(['content']))->forPostType('post');
});

it('refuses a type it does not declare naming it and those it accepts', function (): void {
    $this->requireWooCommerce();

    $this->expectException(SearchTypeRefused::class);
    $this->expectExceptionMessage(
        'Post type "page" is not searchable: declare it in SearchableTypes and index it in MeiliScout. '
        .'Searchable here: product, post.'
    );

    $this->rootOf($this->accepted($this->withWooCommerce()))->type('page');
});

it('refuses a declared type meiliscout does not index', function (): void {
    $page = $this->factory()->forPostType('page');
    $declared = new readonly class($page) implements SearchableTypes
    {
        public function __construct(private SearchableType $page) {}

        public function all(): array
        {
            return ['page' => $this->page];
        }
    };

    $this->expectException(SearchTypeRefused::class);
    $this->expectExceptionMessage('Post type "page" is not searchable');

    $this->rootOf($this->accepted($declared))->type('page');
});

it('refuses a post type wordpress does not know', function (): void {
    $this->expectException(SearchTypeRefused::class);
    $this->expectExceptionMessage('Post type "meili_test_missing" is not registered');

    $this->factory()->forPostType('meili_test_missing');
});

it('leaves products to woocommerce even where they are indexed', function (): void {
    $this->requireWooCommerce();

    $wordPress = new WordPressSearchableTypes(new SearchablePostTypes(new IndexedPostTypes), $this->factory());

    expect(array_keys($wordPress->all()))->toBe(['post']);
});

it('refuses a type searched on a field outside the search order', function (): void {
    $outside = new readonly class($this->withoutWooCommerce()) implements SearchableTypes
    {
        public function __construct(private SearchableTypes $default) {}

        public function all(): array
        {
            $types = $this->default->all();
            $types['post'] = $types['post']->withSearchOn(['post_title', 'url']);

            return $types;
        }
    };

    $this->expectException(UnsearchableFields::class);
    $this->expectExceptionMessage('Post type "post" is searched on fields the index does not search: url.');

    $this->accepted($outside)->all();
});

it('hands out what the decorator of the documentation corrects', function (): void {
    $this->forgetWiring();
    $this->app->scoped(SearchableTypes::class, RetitledSearchableTypes::class);

    try {
        $post = $this->app->make(AcceptedSearchTypes::class)->all()['post'];
    } finally {
        $this->app->scoped(SearchableTypes::class, WooCommerceSearchableTypes::class);
        $this->forgetWiring();
    }

    expect([$post->heading, $post->seeAllLabel])->toBe(['News', 'All the news']);
    expect($post->archive)->toBe(get_post_type_archive_link('post'));
});
