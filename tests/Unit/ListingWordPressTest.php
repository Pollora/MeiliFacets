<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Automattic\WooCommerce\Internal\ProductAttributesLookup\Filterer;
use Automattic\WooCommerce\Internal\ProductAttributesLookup\LookupDataStore;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Listing\ChildTermsFacet;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Listing\PageSize;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Listing\SearchableType;
use Modules\MeiliFacets\Listing\VariationTaxonomies;
use Modules\MeiliFacets\Listing\WooCommerceDefaultSort;
use Modules\MeiliFacets\Listing\WooCommerceFacets;
use Modules\MeiliFacets\Listing\WooCommerceSorts;
use Modules\MeiliFacets\Listing\WordPressDefaultTerms;
use Modules\MeiliFacets\Listing\WordPressTermLabels;
use Modules\MeiliFacets\Listing\WordPressTermScope;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_Term;

/** WooCommerce is "active" the moment `wc_get_product()` exists, so each test gets a process of its own. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ListingWordPressTest extends TestCase
{
    protected function setUp(): void
    {
        WordPress::boot();
        WordPress::bootWooCommerce();
    }

    #[Test]
    public function it_names_each_term_with_the_words_wordpress_stored_it_under(): void
    {
        WordPress::fake('get_terms', [new WP_Term(['slug' => 'acme', 'name' => 'Tom &amp; Co']), 'noise']);
        $labels = new WordPressTermLabels;

        $this->assertSame(['acme' => 'Tom & Co'], $labels->of('product_brand', ['acme']));
        $this->assertSame([], $labels->of('product_brand', []));
        $this->assertSame(1, WordPress::$calls['get_terms']);

        WordPress::fake('get_terms', false);
        $this->assertSame([], $labels->of('product_brand', ['acme']));
    }

    #[Test]
    public function it_scopes_a_taxonomy_to_the_term_being_browsed_and_asks_once_per_taxonomy(): void
    {
        WordPress::fake('get_queried_object', new WP_Term(['term_id' => 12, 'taxonomy' => 'product_cat']));
        $asked = [];
        $hierarchy = new class($asked) implements TermHierarchy
        {
            /** @param  list<array{int, string}>  $asked */
            public function __construct(public array &$asked) {}

            public function ancestorsOf(int $termId, string $taxonomy): array
            {
                return [];
            }

            public function childrenOf(int $termId, string $taxonomy): array
            {
                $this->asked[] = [$termId, $taxonomy];

                return ['shoes'];
            }
        };
        $scope = new WordPressTermScope($hierarchy);

        $this->assertSame(['shoes'], $scope->childrenOfCurrentTerm('product_cat'));
        $this->assertSame(['shoes'], $scope->childrenOfCurrentTerm('product_cat'));
        $scope->childrenOfCurrentTerm('product_brand');

        $this->assertSame([[12, 'product_cat'], [0, 'product_brand']], $asked);
    }

    #[Test]
    public function it_scopes_to_the_root_when_nothing_is_browsed(): void
    {
        WordPress::fake('get_queried_object', null);
        $asked = [];
        $hierarchy = new class($asked) implements TermHierarchy
        {
            /** @param  list<int>  $asked */
            public function __construct(public array &$asked) {}

            public function ancestorsOf(int $termId, string $taxonomy): array
            {
                return [];
            }

            public function childrenOf(int $termId, string $taxonomy): array
            {
                $this->asked[] = $termId;

                return [];
            }
        };

        new WordPressTermScope($hierarchy)->childrenOfCurrentTerm('product_cat');

        $this->assertSame([0], $asked);
    }

    #[Test]
    public function it_finds_the_default_term_of_a_taxonomy_under_either_option_and_remembers_it(): void
    {
        WordPress::fake('get_option', static fn (string $option): int => $option === 'default_product_cat' ? 15 : 0);
        WordPress::fake('get_term', static fn (int $id): WP_Term|false => $id === 15 ? new WP_Term(['slug' => 'uncategorised']) : false);
        $defaults = new WordPressDefaultTerms;

        $this->assertSame('uncategorised', $defaults->slugOf('product_cat'));
        $this->assertSame('uncategorised', $defaults->slugOf('product_cat'));
        $this->assertNull($defaults->slugOf('product_brand'));
        $this->assertSame(2 + 2, WordPress::$calls['get_term']);
    }

    #[Test]
    public function it_offers_the_category_tree_and_the_brand_as_the_shops_facets(): void
    {
        $facets = new WooCommerceFacets(new NameOrder(static fn (): null => null))->all();

        $this->assertInstanceOf(ChildTermsFacet::class, $facets[0]);
        $this->assertSame(['product_cat', 'product_brand'], array_map(static fn (Facet $facet): string => $facet->taxonomy, $facets));
    }

    #[Test]
    public function it_offers_the_four_sorts_a_shop_expects(): void
    {
        $sorts = new WooCommerceSorts;

        $this->assertSame(['price_asc', 'price_desc', 'newest', 'on_sale'], array_keys($sorts->all()));
        $this->assertSame($sorts->all(), $sorts->all());
    }

    #[Test]
    public function it_reads_the_default_sort_from_the_shop_setting_and_lets_a_filter_change_it(): void
    {
        WordPress::fake('get_option', 'price');

        $expressions = new WooCommerceDefaultSort()->expressions();

        $this->assertNotSame([], $expressions);
        $this->assertSame(1, WordPress::$calls['get_option']);

        WordPress::fake('apply_filters', static fn (string $hook, string $saved): string => 'date');
        $filtered = new WooCommerceDefaultSort()->expressions();
        $this->assertNotSame($expressions, $filtered);
    }

    #[Test]
    public function it_sizes_a_page_as_woocommerce_does_and_lets_the_loop_filter_decide(): void
    {
        WordPress::fake('wc_get_default_products_per_row', 4);
        WordPress::fake('wc_get_default_product_rows_per_page', 4);

        $this->assertSame(16, PageSize::forProducts());

        WordPress::fake('apply_filters', static fn (string $hook, int $default): int => $hook === 'loop_shop_per_page' ? 24 : $default);
        $this->assertSame(24, PageSize::forProducts());
    }

    #[Test]
    public function it_lists_only_the_variation_attributes_the_lookup_table_marks(): void
    {
        $lookup = $this->lookupTable(inUse: true, regenerating: false);
        WordPress::fake('wc_get_attribute_taxonomy_names', ['a' => 'pa_color', 'b' => 'pa_size', 'c' => 'pa_material']);
        WordPress::fake('wc_get_container', $lookup);
        $GLOBALS['wpdb'] = new class
        {
            /** @return list<string> */
            public function get_col(string $query): array
            {
                return ['pa_size', 'pa_color'];
            }
        };

        $taxonomies = new VariationTaxonomies;

        $this->assertSame(['pa_color', 'pa_size'], $taxonomies->all());
        $this->assertSame(['pa_color', 'pa_size'], $taxonomies->all());
        $this->assertSame(1, WordPress::$calls['wc_get_attribute_taxonomy_names']);
    }

    #[Test]
    public function it_lists_every_attribute_while_the_lookup_table_is_not_trustworthy(): void
    {
        WordPress::fake('wc_get_attribute_taxonomy_names', ['pa_color', 'pa_size']);

        WordPress::fake('wc_get_container', $this->lookupTable(inUse: false, regenerating: false));
        $this->assertSame(['pa_color', 'pa_size'], new VariationTaxonomies()->all());

        WordPress::fake('wc_get_container', $this->lookupTable(inUse: true, regenerating: true));
        $this->assertSame(['pa_color', 'pa_size'], new VariationTaxonomies()->all());
    }

    #[Test]
    public function it_falls_back_to_every_attribute_and_reports_when_woocommerce_internals_moved(): void
    {
        WordPress::fake('wc_get_attribute_taxonomy_names', ['pa_color']);
        WordPress::fake('wc_get_container', static fn (): never => throw new RuntimeException('internal class gone'));

        $this->assertSame(['pa_color'], new VariationTaxonomies()->all());
    }

    #[Test]
    public function it_reads_its_name_apply_mode_page_size_and_variant_taxonomies(): void
    {
        $listing = $this->productListing('immediate');

        $this->assertSame('products', $listing->name());
        $this->assertSame('immediate', $listing->applyMode()->value);
        $this->assertSame(['pa_size'], $listing->variantTaxonomies());
        $this->assertSame(WordPress::call('wc_get_default_products_per_row', []) * 3, $listing->perPage());
    }

    #[Test]
    public function it_pins_the_browsed_taxonomy_and_narrows_the_other_facets_under_it(): void
    {
        $listing = $this->productListing(facets: [new Facet('product_cat', 'Category'), new Facet('product_brand', 'Brand')]);
        WordPress::fake('get_queried_object', new WP_Term(['taxonomy' => 'product_cat', 'slug' => 'shoes']));
        WordPress::fake('is_object_in_taxonomy', true);

        $this->assertSame(['product_brand'], array_map(static fn (Facet $facet): string => $facet->taxonomy, $listing->facets()));
        $this->assertCount(2, $listing->filters());
        $this->assertContains('facets.product_cat = "shoes"', $listing->baseFilter());
        $this->assertContains('facets.product_cat = "shoes"', $listing->searchScope()->filter);
        $this->assertContains('facets.product_cat = "shoes"', $listing->variantFilter());
        $this->assertContains('facets.product_cat = "shoes"', $listing->variantSearchScope()->filter);
    }

    #[Test]
    public function it_does_not_pin_a_term_the_products_cannot_be_filed_under(): void
    {
        $listing = $this->productListing();
        WordPress::fake('get_queried_object', new WP_Term(['taxonomy' => 'post_tag', 'slug' => 'news']));
        WordPress::fake('is_object_in_taxonomy', false);

        $this->assertCount(2, $listing->facets());
        $this->assertNotContains('facets.post_tag = "news"', $listing->baseFilter());
    }

    #[Test]
    public function it_borrows_the_scope_of_the_product_search_type_when_the_site_declares_one(): void
    {
        WordPress::fake('get_queried_object', null);
        $type = new SearchableType('product', 'Products', 'See all', ['custom = 1'], ['post_title'], 'card', null);

        $listing = $this->productListing(types: ['product' => $type]);

        $this->assertSame(['custom = 1'], $listing->searchScope()->filter);
        $this->assertSame(['post_title'], $listing->searchScope()->fields);
    }

    #[Test]
    public function it_offers_the_on_sale_sort_only_when_the_listing_declares_a_price_range(): void
    {
        WordPress::fake('get_queried_object', null);

        $without = $this->productListing()->sorts();
        $this->assertArrayNotHasKey('on_sale', $without);
        $this->assertArrayHasKey('price_asc', $without);

        $with = $this->productListing(facets: [new Facet('product_brand', 'Brand'), new PriceFilter('Price')])->sorts();
        $this->assertArrayHasKey('on_sale', $with);
        $this->assertNotSame([], $this->productListing()->defaultSort());
    }

    #[Test]
    public function it_reads_the_search_term_only_on_a_search_page_and_trims_it(): void
    {
        $listing = $this->productListing();

        WordPress::fake('is_search', false);
        $this->assertSame('', $listing->baseQuery());

        WordPress::fake('is_search', true);
        WordPress::fake('get_query_var', '  red shoes  ');
        $this->assertSame('red shoes', $listing->baseQuery());
    }

    /**
     * @param  array<string, SearchableType>  $types
     * @param  list<Placeable>|null  $facets
     */
    private function productListing(string $applyMode = 'submit', array $types = [], ?array $facets = null): ProductListing
    {
        WordPress::fake('wc_get_default_products_per_row', 3);
        WordPress::fake('wc_get_default_product_rows_per_page', 3);
        WordPress::fake('wc_get_attribute_taxonomy_names', ['pa_size']);
        WordPress::fake('wc_get_container', $this->lookupTable(inUse: false, regenerating: false));
        WordPress::fake('get_option', 'menu_order');

        $facets ??= new WooCommerceFacets(new NameOrder(static fn (): null => null))->all();
        $productFacets = new readonly class($facets) implements ProductFacets
        {
            /** @param  list<Placeable>  $facets */
            public function __construct(private array $facets) {}

            public function all(): array
            {
                return $this->facets;
            }
        };
        $searchable = new readonly class($types) implements SearchableTypes
        {
            /** @param  array<string, SearchableType>  $types */
            public function __construct(private array $types) {}

            public function all(): array
            {
                return $this->types;
            }
        };

        return new ProductListing(
            $productFacets,
            new WooCommerceSorts,
            $searchable,
            new VariationTaxonomies,
            new WooCommerceDefaultSort,
            $applyMode,
        );
    }

    private function lookupTable(bool $inUse, bool $regenerating): object
    {
        Filterer::$active = $inUse;
        LookupDataStore::$regenerating = $regenerating;

        return new class
        {
            public function get(string $class): object
            {
                return new $class;
            }
        };
    }
}
