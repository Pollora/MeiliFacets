<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Indexing\IndexedPostTypes;
use Modules\MeiliFacets\Indexing\WooCommerceProductFields;
use Modules\MeiliFacets\SiteSearch\AcceptedSearchTypes;
use Modules\MeiliFacets\SiteSearch\AttributesToSearchOn;
use Modules\MeiliFacets\SiteSearch\FieldsOutsideSearchOrder;
use Modules\MeiliFacets\SiteSearch\NoFieldToSearch;
use Modules\MeiliFacets\SiteSearch\SearchablePostTypes;
use Modules\MeiliFacets\SiteSearch\SearchableType;
use Modules\MeiliFacets\SiteSearch\SearchableTypeFactory;
use Modules\MeiliFacets\SiteSearch\SearchRoot;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\SiteSearch\SearchTypeRefused;
use Modules\MeiliFacets\SiteSearch\WooCommerceSearchableTypes;
use Modules\MeiliFacets\SiteSearch\WordPressSearchableTypes;
use Modules\MeiliFacets\Tests\Unit\Doubles\RetitledSearchableTypes;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WooCommerce;

/** Runs here rather than standalone: labels, archives, taxonomies and the indexed types are WordPress's. */
final class SearchableTypesTest extends TestCase
{
    use PinsIndexedPostTypes;

    private const string NOTE = 'meili_test_note';

    private const string HIDDEN = 'meili_test_hidden';

    /** Resolved once per request by the container: a binding changed in a test needs them forgotten. */
    private const array WIRING = [
        SearchableTypes::class,
        WordPressSearchableTypes::class,
        WooCommerceSearchableTypes::class,
        AcceptedSearchTypes::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinIndexedPostTypes();
    }

    protected function tearDown(): void
    {
        $this->unpinIndexedPostTypes();
        unregister_post_type(self::NOTE);
        unregister_post_type(self::HIDDEN);

        parent::tearDown();
    }

    #[Test]
    public function it_binds_the_woocommerce_declaration_when_the_project_says_nothing(): void
    {
        $this->assertInstanceOf(WooCommerceSearchableTypes::class, $this->app->make(SearchableTypes::class));
    }

    #[Test]
    public function it_declares_an_indexed_searchable_post_type_without_code(): void
    {
        register_post_type(self::NOTE, [
            'public' => true,
            'labels' => ['name' => 'Notes', 'all_items' => 'All notes'],
        ]);
        $this->index(['post', self::NOTE]);

        $note = $this->withoutWooCommerce()->all()[self::NOTE];

        $this->assertSame(
            ['Notes', 'All notes', SearchableTypeFactory::CARD, null],
            [$note->heading, $note->seeAllLabel, $note->card, $note->archive]
        );
        $this->assertSame(['post_type = "meili_test_note"', 'post_status = "publish"'], $note->baseFilter);
    }

    #[Test]
    public function it_leaves_out_a_type_hidden_from_search_or_left_out_of_the_index(): void
    {
        register_post_type(self::HIDDEN, ['public' => true, 'exclude_from_search' => true]);
        $this->index(['post', self::HIDDEN]);

        $this->assertSame(['post'], array_keys($this->withoutWooCommerce()->all()));
    }

    #[Test]
    public function it_reads_the_labels_and_the_archive_wordpress_gives_each_type(): void
    {
        $this->requireWooCommerce();

        foreach ($this->withWooCommerce()->all() as $postType => $type) {
            $labels = get_post_type_object($postType)->labels;

            $this->assertSame(
                [$labels->name, $labels->all_items, get_post_type_archive_link($postType)],
                [$type->heading, $type->seeAllLabel, $type->archive],
                $postType
            );
        }
    }

    #[Test]
    public function it_leads_nowhere_for_a_type_without_archive(): void
    {
        $this->assertNull($this->factory()->forPostType('page')->archive);
    }

    #[Test]
    public function it_declares_products_first_then_the_other_types_in_the_order_they_are_indexed(): void
    {
        $this->requireWooCommerce();
        register_post_type(self::NOTE, ['public' => true]);
        $this->index([self::NOTE, 'post', 'product']);

        $this->assertSame(['product', self::NOTE, 'post'], array_keys($this->withWooCommerce()->all()));
    }

    #[Test]
    public function it_declares_no_product_without_woocommerce(): void
    {
        $this->assertSame(['post'], array_keys($this->withoutWooCommerce()->all()));
    }

    #[Test]
    public function it_searches_each_type_within_the_index_search_order(): void
    {
        $this->requireWooCommerce();

        $order = $this->app->make(SearchableAttributes::class)->all();

        foreach ($this->withWooCommerce()->all() as $type) {
            $this->assertSame([], array_diff($type->searchOn, $order), $type->postType);
        }
    }

    #[Test]
    public function it_searches_posts_by_title_labels_and_excerpt_and_products_by_what_names_them(): void
    {
        $this->requireWooCommerce();

        $types = $this->withWooCommerce()->all();
        $postLabels = array_map(
            static fn (string $taxonomy): string => 'labels.'.$taxonomy,
            get_object_taxonomies('post')
        );

        $this->assertSame(
            ['post_title', 'labels.product_brand', 'labels.product_cat', 'metas._sku'],
            $types['product']->searchOn
        );
        $this->assertSame(['post_title', ...$postLabels, 'excerpt'], $types['post']->searchOn);
    }

    #[Test]
    public function it_follows_an_order_the_project_narrowed(): void
    {
        $type = $this->factory($this->order(['post_title', 'content']))->forPostType('post');

        $this->assertSame(['post_title'], $type->searchOn);
    }

    #[Test]
    public function it_refuses_a_type_the_order_leaves_nothing_to_search(): void
    {
        $this->expectException(NoFieldToSearch::class);
        $this->expectExceptionMessage('Post type "post" has no field to search');

        $this->factory($this->order(['content']))->forPostType('post');
    }

    #[Test]
    public function it_refuses_a_type_it_does_not_declare_naming_it_and_those_it_accepts(): void
    {
        $this->requireWooCommerce();

        $this->expectException(SearchTypeRefused::class);
        $this->expectExceptionMessage(
            'Post type "page" is not searchable: declare it in SearchableTypes and index it in MeiliScout. '
            .'Searchable here: product, post.'
        );

        $this->rootOf($this->accepted($this->withWooCommerce()))->type('page');
    }

    #[Test]
    public function it_refuses_a_declared_type_meiliscout_does_not_index(): void
    {
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
    }

    #[Test]
    public function it_refuses_a_post_type_wordpress_does_not_know(): void
    {
        $this->expectException(SearchTypeRefused::class);
        $this->expectExceptionMessage('Post type "meili_test_missing" is not registered');

        $this->factory()->forPostType('meili_test_missing');
    }

    #[Test]
    public function it_leaves_products_to_woocommerce_even_where_they_are_indexed(): void
    {
        $this->requireWooCommerce();

        $wordPress = new WordPressSearchableTypes(new SearchablePostTypes(new IndexedPostTypes), $this->factory());

        $this->assertSame(['post'], array_keys($wordPress->all()));
    }

    #[Test]
    public function it_refuses_a_type_searched_on_a_field_outside_the_search_order(): void
    {
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

        $this->expectException(FieldsOutsideSearchOrder::class);
        $this->expectExceptionMessage('Post type "post" is searched on fields the index does not search: url.');

        $this->accepted($outside)->all();
    }

    #[Test]
    public function it_hands_out_what_the_decorator_of_the_documentation_corrects(): void
    {
        $this->forgetWiring();
        $this->app->scoped(SearchableTypes::class, RetitledSearchableTypes::class);

        try {
            $post = $this->app->make(AcceptedSearchTypes::class)->all()['post'];
        } finally {
            $this->app->scoped(SearchableTypes::class, WooCommerceSearchableTypes::class);
            $this->forgetWiring();
        }

        $this->assertSame(['News', 'All the news'], [$post->heading, $post->seeAllLabel]);
        $this->assertSame(get_post_type_archive_link('post'), $post->archive);
    }

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
