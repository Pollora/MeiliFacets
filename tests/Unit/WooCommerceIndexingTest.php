<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Indexing\WooCommerce\AnonymousVisitor;
use Modules\MeiliFacets\Indexing\WooCommerce\EmptyVariantFields;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductOrderingChanges;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductPriceProjector;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductStock;
use Modules\MeiliFacets\Indexing\WooCommerce\ProductVariants;
use Modules\MeiliFacets\Indexing\WooCommerce\ShopFields;
use Modules\MeiliFacets\Indexing\WooCommerce\ShopTaxLocation;
use Modules\MeiliFacets\Indexing\WooCommerce\VariationChanges;
use Modules\MeiliFacets\Indexing\WooCommerce\VariationMetaRewrites;
use Modules\MeiliFacets\Indexing\WooCommerce\WooCommerceCardProjector;
use Modules\MeiliFacets\Support\Money;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use WC_Product;
use WC_Product_Grouped;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Post;
use WP_Term;

/** WooCommerce is "active" the moment `wc_get_product()` exists, so each test gets a process of its own. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class WooCommerceIndexingTest extends TestCase
{
    protected function setUp(): void
    {
        WordPress::boot();
        WordPress::bootWooCommerce();
    }

    #[Test]
    public function it_formats_a_price_as_the_shop_shows_it_without_markup_or_entities(): void
    {
        WordPress::fake('wc_price', '<span>&euro;&nbsp;12,50</span>');
        WordPress::fake('wp_strip_all_tags', static fn (string $html): string => strip_tags($html));

        $this->assertSame("€\u{a0}12,50", new Money()->of(12.5));
        $this->assertSame('', new Money()->of(null));
    }

    #[Test]
    public function it_describes_the_shop_currency_for_the_browser(): void
    {
        WordPress::fake('get_woocommerce_currency_symbol', '&euro;');
        WordPress::fake('get_woocommerce_price_format', '%2$s&nbsp;%1$s');
        WordPress::fake('wc_get_price_decimals', 2);
        WordPress::fake('wc_get_price_decimal_separator', ',');
        WordPress::fake('wc_get_price_thousand_separator', ' ');
        $money = new Money;

        $this->assertSame('€', $money->symbol());
        $this->assertSame([
            'format' => "%2\$s\u{a0}%1\$s",
            'symbol' => '€',
            'decimals' => 2,
            'decimal' => ',',
            'thousand' => ' ',
        ], $money->describe());
    }

    #[Test]
    public function it_projects_the_price_range_a_simple_product_shows(): void
    {
        WordPress::fake('wc_get_product', new WC_Product(['id' => 4, 'price' => '19.9']));
        WordPress::fake('wc_get_price_to_display', 23.88);
        $this->fakeSalePrices([4 => ['_price' => '19.9', '_sale_price' => '19.9']]);

        $this->assertSame(
            ['min' => 23.88, 'max' => 23.88, 'onsale' => true],
            new ProductPriceProjector()->project(new WP_Post)
        );
    }

    #[Test]
    public function it_projects_nothing_for_a_product_without_a_price_or_a_non_product(): void
    {
        $projector = new ProductPriceProjector;

        WordPress::fake('wc_get_product', false);
        $this->assertSame([], $projector->project(new WP_Post));

        WordPress::fake('wc_get_product', new WC_Product(['price' => '']));
        $this->assertSame([], $projector->project(new WP_Post));
    }

    #[Test]
    public function it_projects_the_range_between_the_cheapest_and_the_dearest_variation(): void
    {
        $variable = new WC_Product_Variable(['id' => 5, 'prices' => [11 => '10', 12 => '30'], 'children' => [11, 12]]);
        WordPress::fake('wc_get_product', $variable);
        WordPress::fake('update_meta_cache', null);
        $this->fakeSalePrices([11 => ['_price' => '10', '_sale_price' => ''], 12 => ['_price' => '30', '_sale_price' => '30']]);

        $this->assertSame(
            ['min' => 10.0, 'max' => 30.0, 'onsale' => true],
            new ProductPriceProjector()->project(new WP_Post)
        );
    }

    #[Test]
    public function it_projects_nothing_for_a_variable_product_without_variation_prices(): void
    {
        WordPress::fake('wc_get_product', new WC_Product_Variable(['prices' => []]));

        $this->assertSame([], new ProductPriceProjector()->project(new WP_Post));
    }

    #[Test]
    public function it_projects_the_range_of_the_children_of_a_grouped_product_and_never_lists_it_on_sale(): void
    {
        $children = [new WC_Product(['id' => 1, 'price' => '5']), new WC_Product(['id' => 2, 'price' => '']), new WC_Product(['id' => 3, 'price' => '9'])];
        $grouped = new class(['children' => $children]) extends WC_Product_Grouped {};
        WordPress::fake('wc_get_product', $grouped);
        WordPress::fake('wc_get_price_to_display', static fn (WC_Product $product): float => (float) $product->get_price());

        $this->assertSame(
            ['min' => 5.0, 'max' => 9.0, 'onsale' => false],
            new ProductPriceProjector()->project(new WP_Post)
        );

        $empty = new WC_Product_Grouped(['children' => []]);
        WordPress::fake('wc_get_product', $empty);
        $this->assertSame([], new ProductPriceProjector()->project(new WP_Post));
    }

    #[Test]
    public function it_flags_a_product_out_of_stock_only_when_it_is(): void
    {
        $stock = new ProductStock;

        WordPress::fake('wc_get_product', new WC_Product(['in_stock' => false]));
        $this->assertSame(['out_of_stock' => true], $stock->project(new WP_Post));

        WordPress::fake('wc_get_product', new WC_Product(['in_stock' => true]));
        $this->assertSame([], $stock->project(new WP_Post));

        WordPress::fake('wc_get_product', false);
        $this->assertSame([], $stock->project(new WP_Post));
    }

    #[Test]
    public function it_projects_the_card_of_a_product_with_its_price_markup(): void
    {
        WordPress::fake('wc_get_product', new WC_Product(['price_html' => '<span>10</span>']));
        $projector = new WooCommerceCardProjector($this->card(['title' => 'T']), $this->card(['title' => 'Other']));

        $this->assertSame(['title' => 'T', 'price' => '<span>10</span>'], $projector->project(new WP_Post));

        WordPress::fake('wc_get_product', false);
        $this->assertSame(['title' => 'Other'], $projector->project(new WP_Post));
    }

    #[Test]
    public function it_projects_the_variants_of_a_variable_product_with_the_terms_of_their_attributes(): void
    {
        $red = new WC_Product_Variation([
            'id' => 11, 'attributes' => ['pa_color' => 'red', 'pa_size' => '', 'note' => 'x'], 'in_stock' => true,
            'on_sale' => true, 'price_html' => '<b>10</b>', 'permalink' => 'https://example.test/red', 'image_id' => 0,
        ]);
        $unpriced = new WC_Product_Variation(['id' => 12]);
        WordPress::fake('wc_get_product', new WC_Product_Variable(['prices' => [11 => '10'], 'variations' => [$red, $unpriced]]));
        WordPress::fake('_prime_post_caches', null);
        WordPress::fake('taxonomy_is_product_attribute', static fn (string $taxonomy): bool => str_starts_with($taxonomy, 'pa_'));
        WordPress::fake('is_taxonomy_hierarchical', true);
        WordPress::fake('get_term_by', new WP_Term(['term_id' => 8]));
        $hierarchy = new class implements TermHierarchy
        {
            public function ancestorsOf(int $termId, string $taxonomy): array
            {
                return [['slug' => 'warm'], ['slug' => 'colour']];
            }

            public function childrenOf(int $termId, string $taxonomy): array
            {
                return [];
            }
        };

        $variants = new ProductVariants(new EmptyVariantFields, $hierarchy, 'medium')->project(new WP_Post);

        $this->assertCount(1, $variants);
        $this->assertSame(['pa_color' => ['red', 'warm', 'colour']], $variants[0]['facets']);
        $this->assertSame(10.0, $variants[0]['price']);
        $this->assertTrue($variants[0]['on_sale']);
        $this->assertSame('https://example.test/red', $variants[0]['fields']['url']);
    }

    #[Test]
    public function it_projects_no_variants_for_a_simple_product_or_a_flat_attribute(): void
    {
        $variants = new ProductVariants(new EmptyVariantFields, $this->noHierarchy(), 'medium');

        WordPress::fake('wc_get_product', new WC_Product);
        $this->assertSame([], $variants->project(new WP_Post));

        $variation = new WC_Product_Variation(['id' => 1, 'attributes' => ['pa_size' => 'm']]);
        WordPress::fake('wc_get_product', new WC_Product_Variable(['prices' => [1 => '5'], 'variations' => [$variation]]));
        WordPress::fake('_prime_post_caches', null);
        WordPress::fake('taxonomy_is_product_attribute', true);
        WordPress::fake('is_taxonomy_hierarchical', false);

        $this->assertSame(['pa_size' => ['m']], $variants->project(new WP_Post)[0]['facets']);
    }

    #[Test]
    public function it_reads_the_shop_as_an_anonymous_visitor_at_the_shop_address(): void
    {
        $countries = new class
        {
            public function get_base_country(): string
            {
                return 'FR';
            }

            public function get_base_state(): string
            {
                return '';
            }

            public function get_base_postcode(): string
            {
                return '75001';
            }

            public function get_base_city(): string
            {
                return 'Paris';
            }
        };
        $filters = [];
        WordPress::fake('add_filter', static function (string $hook, callable $callback) use (&$filters): void {
            $filters[$hook] = $callback;
        });
        WordPress::fake('remove_filter', static function (string $hook) use (&$filters): void {
            unset($filters[$hook]);
        });
        WordPress::fake('WC', (object) ['countries' => $countries]);
        $users = [];
        WordPress::fake('get_current_user_id', 42);
        WordPress::fake('wp_set_current_user', static function (int $id) use (&$users): void {
            $users[] = $id;
        });

        $seen = new ShopTaxLocation()->during(function () use (&$filters, &$users): array {
            return [$filters['woocommerce_get_tax_location'](), new AnonymousVisitor()->during(static fn (): string => 'read'), $users];
        });

        $this->assertSame(['FR', '', '75001', 'Paris'], $seen[0]);
        $this->assertSame('read', $seen[1]);
        $this->assertSame([0, 42], $users);
        $this->assertSame([], $filters);
    }

    #[Test]
    public function it_restores_the_visitor_and_the_tax_location_when_the_read_fails(): void
    {
        $restored = [];
        WordPress::fake('remove_filter', static function () use (&$restored): void {
            $restored[] = 'filter';
        });
        WordPress::fake('get_current_user_id', 7);
        WordPress::fake('wp_set_current_user', static function (int $id) use (&$restored): void {
            $restored[] = $id;
        });

        try {
            new ShopTaxLocation()->during(static fn () => new AnonymousVisitor()->during(static fn () => throw new \RuntimeException('boom')));
            $this->fail('The failure was swallowed.');
        } catch (\RuntimeException) {
            $this->assertSame([0, 7, 'filter'], $restored);
        }
    }

    #[Test]
    public function it_projects_the_shop_fields_of_a_product_in_a_card_with_its_price_and_stock(): void
    {
        $variation = new WC_Product_Variation(['id' => 11, 'attributes' => []]);
        WordPress::fake('wc_get_product', new WC_Product_Variable(['id' => 5, 'in_stock' => false, 'prices' => [11 => '10'], 'children' => [11], 'variations' => [$variation]]));
        WordPress::fake('_prime_post_caches', null);
        WordPress::fake('update_meta_cache', null);
        WordPress::fake('wc_format_decimal', static fn (mixed $value): string => (string) $value);
        WordPress::fake('get_post_meta', '');
        WordPress::fake('get_current_user_id', 0);
        WordPress::fake('wp_set_current_user', null);
        WordPress::fake('WC', (object) ['countries' => new class
        {
            public function __call(string $name, array $arguments): string
            {
                return '';
            }
        }]);

        $fields = $this->shopFields($this->card(['title' => 'T', 'out_of_stock' => true, 'variants' => 'stale']))->project(new WP_Post);

        $this->assertSame(0, $fields['in_stock']);
        $this->assertSame('parent', $fields['document_kind']);
        $this->assertSame(10.0, $fields['price']['min']);
        $this->assertSame('T', $fields['card']['title']);
        $this->assertTrue($fields['card']['out_of_stock']);
        $this->assertCount(1, $fields['card']['variants']);
    }

    #[Test]
    public function it_projects_only_the_card_of_a_post_that_is_not_for_sale(): void
    {
        WordPress::fake('wc_get_product', false);
        WordPress::fake('get_current_user_id', 0);
        WordPress::fake('wp_set_current_user', null);
        WordPress::fake('WC', (object) ['countries' => new class
        {
            public function __call(string $name, array $arguments): string
            {
                return '';
            }
        }]);

        $this->assertSame(['card' => ['title' => 'T']], $this->shopFields($this->card(['title' => 'T']))->project(new WP_Post));
    }

    #[Test]
    public function it_reindexes_the_parent_of_each_changed_variation_once_at_shutdown(): void
    {
        WordPress::fake('wp_get_post_parent_id', static fn (int $id): int => $id > 100 ? 0 : 7);
        WordPress::fake('do_action', null);
        $changes = new VariationChanges;

        $changes->rememberProductOf(11);
        $changes->rememberProductOf(12);
        $changes->rememberProductOf(101);
        $changes->reindexChangedProducts();
        $changes->reindexChangedProducts();

        $reindexed = array_values(array_filter(WordPress::$log, static fn (array $call): bool => $call[0] === 'do_action'));
        $this->assertSame([['meiliscout/reindex_post', 7]], array_column($reindexed, 1));
    }

    #[Test]
    public function it_remembers_a_product_when_a_meta_of_its_variation_changes(): void
    {
        WordPress::fake('get_post_type', static fn (int $id): string => $id === 11 ? 'product_variation' : 'post');
        WordPress::fake('wp_get_post_parent_id', 7);
        WordPress::fake('do_action', null);
        $changes = new VariationChanges;

        $changes->rememberProductOfVariationMeta(1, 0);
        $changes->rememberProductOfVariationMeta(1, 5);
        $changes->rememberProductOfVariationMeta(1, 11);
        $changes->reindexChangedProducts();

        $this->assertSame(1, WordPress::$calls['do_action']);
    }

    #[Test]
    public function it_reindexes_the_products_moved_by_the_ordering_request_only(): void
    {
        WordPress::fake('doing_action', static fn (string $hook): bool => WordPress::$config['ordering'] ?? false);
        WordPress::fake('do_action', null);
        $ordering = new ProductOrderingChanges;

        $ordering->rememberMoved(1);
        WordPress::$config['ordering'] = true;
        $ordering->rememberMoved(2);
        $ordering->reindexMoved();
        $ordering->reindexMoved();

        $this->assertSame(1, WordPress::$calls['do_action']);
        $this->assertSame(['meiliscout/reindex_post', 2], WordPress::$log[array_key_last(WordPress::$log)][1]);
    }

    #[Test]
    public function it_clears_the_cache_of_the_products_and_variations_using_an_edited_term(): void
    {
        WordPress::fake('taxonomy_is_product_attribute', static fn (string $taxonomy): bool => $taxonomy === 'pa_color');
        WordPress::fake('get_term', new WP_Term(['term_id' => 3, 'slug' => 'red', 'taxonomy' => 'pa_color']));
        WordPress::fake('wc_variation_attribute_name', 'attribute_pa_color');
        WordPress::fake('get_objects_in_term', ['10', '11']);
        WordPress::fake('is_wp_error', false);
        $queried = [];
        WordPress::fake('get_posts', static function (array $criteria) use (&$queried): array {
            $queried[] = $criteria;

            return [20];
        });
        WordPress::fake('clean_post_cache', null);
        $rewrites = new VariationMetaRewrites;

        $rewrites->clearCacheOfTerm(3, 3, 'pa_color');

        $this->assertSame([10, 11, 20], $this->cleaned());
        $this->assertSame('red', $queried[0]['meta_value']);
        $this->assertSame('attribute_pa_color', $queried[0]['meta_key']);
    }

    #[Test]
    public function it_ignores_an_edited_term_that_is_not_a_product_attribute_or_no_longer_exists(): void
    {
        WordPress::fake('taxonomy_is_product_attribute', static fn (string $taxonomy): bool => $taxonomy === 'pa_color');
        WordPress::fake('get_term', false);
        $rewrites = new VariationMetaRewrites;

        $rewrites->clearCacheOfTerm(3, 3, 'category');
        $this->assertArrayNotHasKey('get_term', WordPress::$calls);

        $rewrites->clearCacheOfTerm(3, 3, 'pa_color');
        $this->assertSame(1, WordPress::$calls['get_term']);
    }

    #[Test]
    public function it_survives_a_term_whose_products_cannot_be_listed(): void
    {
        WordPress::fake('taxonomy_is_product_attribute', true);
        WordPress::fake('get_term', new WP_Term(['term_id' => 3, 'slug' => 'red', 'taxonomy' => 'pa_color']));
        WordPress::fake('wc_variation_attribute_name', 'attribute_pa_color');
        WordPress::fake('get_objects_in_term', new stdClass);
        WordPress::fake('is_wp_error', true);
        WordPress::fake('get_posts', [30]);
        WordPress::fake('clean_post_cache', null);

        new VariationMetaRewrites()->clearCacheOfTerm(3, 3, 'pa_color');

        $this->assertSame([30], $this->cleaned());
    }

    #[Test]
    public function it_reindexes_the_whole_shop_once_an_attribute_was_renamed(): void
    {
        WordPress::fake('wc_attribute_taxonomy_name', static fn (string $name): string => 'pa_'.$name);
        WordPress::fake('wc_variation_attribute_name', static fn (string $taxonomy): string => 'attribute_'.$taxonomy);
        WordPress::fake('get_posts', [40, 41]);
        WordPress::fake('clean_post_cache', null);
        WordPress::fake('do_action', null);
        $rewrites = new VariationMetaRewrites;

        $rewrites->reindexRenamedAttributes();
        $this->assertArrayNotHasKey('do_action', WordPress::$calls);

        $rewrites->rememberRenamedAttribute(1, ['attribute_name' => 'colour'], 'colour');
        $rewrites->rememberRenamedAttribute(1, ['attribute_name' => 'colour'], 'color');
        $rewrites->reindexRenamedAttributes();
        $rewrites->reindexRenamedAttributes();

        $this->assertSame([40, 41], $this->cleaned());
        $this->assertSame(1, WordPress::$calls['do_action']);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function card(array $fields): CardProjector
    {
        return new class($fields) implements CardProjector
        {
            /** @param  array<string, mixed>  $fields */
            public function __construct(private readonly array $fields) {}

            public function project(WP_Post $post): array
            {
                return $this->fields;
            }
        };
    }

    private function noHierarchy(): TermHierarchy
    {
        return new class implements TermHierarchy
        {
            public function ancestorsOf(int $termId, string $taxonomy): array
            {
                return [];
            }

            public function childrenOf(int $termId, string $taxonomy): array
            {
                return [];
            }
        };
    }

    private function shopFields(CardProjector $card): ShopFields
    {
        return new ShopFields(
            $card,
            new ProductVariants(new EmptyVariantFields, $this->noHierarchy(), 'medium'),
            new ProductStock,
            new ProductPriceProjector,
            new ShopTaxLocation,
            new AnonymousVisitor,
        );
    }

    /**
     * @param  array<int, array<string, string>>  $metas
     */
    private function fakeSalePrices(array $metas): void
    {
        WordPress::fake('wc_format_decimal', static fn (mixed $value): string => (string) $value);
        WordPress::fake('get_post_meta', static fn (int $id, string $key): string => $metas[$id][$key] ?? '');
    }

    /**
     * @return list<int>
     */
    private function cleaned(): array
    {
        $calls = array_filter(WordPress::$log, static fn (array $call): bool => $call[0] === 'clean_post_cache');

        return array_values(array_map(static fn (array $call): int => (int) $call[1][0], $calls));
    }
}
