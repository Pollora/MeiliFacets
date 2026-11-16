<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Automattic\WooCommerce\Internal\ProductAttributesLookup\LookupDataStore;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Exceptions;
use Modules\MeiliFacets\Listing\VariationTaxonomies;
use RuntimeException;
use stdClass;
use Tests\TestCase;
use WC_Cache_Helper;
use WooCommerce;

trait VariationTaxonomiesSteps
{
    private const string SIZE = 'meilifacets_test_size';

    private const string SKIN = 'meilifacets_test_skin';

    private const string ATTRIBUTES = 'woocommerce_attribute_taxonomies';

    private const string LOOKUP_ENABLED = 'pre_option_woocommerce_attribute_lookup_enabled';

    private const string REGENERATING = 'pre_option_woocommerce_attribute_lookup_regeneration_in_progress';

    private const string ATTRIBUTE_CACHE = 'woocommerce-attributes';

    private const string BROKEN = 'broken';

    /** Far above any real post: the rows are told apart from the shop's by it. */
    private const int PRODUCT_ID = 2_000_000_001;

    private const int ATTRIBUTE_ID_BASE = 2_000_000_000;

    /** Each `$this->method(...)` is a new closure, which `remove_filter()` would not find. */
    private ?Closure $attributes = null;

    private Closure $lookupEnabledAnswer;

    private Closure $regeneratingAnswer;

    private string $lookupEnabled = 'yes';

    private string $regenerating = 'no';

    private ?ExceptionHandler $hostHandler = null;

    /**
     * @param  array<object>  $attributes
     * @return array<object>
     */
    private function withTestAttributes(array $attributes): array
    {
        return [...$attributes, $this->attribute(1, self::SIZE), $this->attribute(2, self::SKIN)];
    }

    /** WooCommerce keys the attributes by ID: two sharing one would collapse into the last. */
    private function attribute(int $offset, string $name): stdClass
    {
        return (object) ['attribute_id' => self::ATTRIBUTE_ID_BASE + $offset, 'attribute_name' => $name, 'attribute_label' => $name, 'attribute_type' => 'select', 'attribute_orderby' => 'menu_order', 'attribute_public' => 0];
    }

    private function insertLookupRow(string $attribute, bool $isVariation): void
    {
        global $wpdb;

        $wpdb->insert($this->lookupTable(), [
            'product_id' => self::PRODUCT_ID,
            'product_or_parent_id' => self::PRODUCT_ID,
            'taxonomy' => 'pa_'.$attribute,
            'term_id' => 1,
            'is_variation_attribute' => (int) $isVariation,
            'in_stock' => 1,
        ]);
    }

    private function lookupTable(): string
    {
        return wc_get_container()->get(LookupDataStore::class)->get_lookup_table_name();
    }
}

uses(TestCase::class, VariationTaxonomiesSteps::class);

beforeEach(function (): void {
    if (! class_exists(WooCommerce::class)) {
        $this->markTestSkipped('Attributes are WooCommerce\'s.');
    }

    $this->attributes = $this->withTestAttributes(...);
    $this->lookupEnabledAnswer = fn (): string => $this->lookupEnabled === self::BROKEN
        ? throw new RuntimeException('WooCommerce changed its internal lookup classes.')
        : $this->lookupEnabled;
    $this->regeneratingAnswer = fn (): string => $this->regenerating;
    add_filter(self::ATTRIBUTES, $this->attributes);
    add_filter(self::LOOKUP_ENABLED, $this->lookupEnabledAnswer);
    add_filter(self::REGENERATING, $this->regeneratingAnswer);
    WC_Cache_Helper::invalidate_cache_group(self::ATTRIBUTE_CACHE);
    $this->insertLookupRow(self::SIZE, isVariation: true);
    $this->insertLookupRow(self::SKIN, isVariation: false);
});

afterEach(function (): void {
    global $wpdb;

    if ($this->attributes instanceof Closure) {
        remove_filter(self::ATTRIBUTES, $this->attributes);
        remove_filter(self::LOOKUP_ENABLED, $this->lookupEnabledAnswer);
        remove_filter(self::REGENERATING, $this->regeneratingAnswer);
        WC_Cache_Helper::invalidate_cache_group(self::ATTRIBUTE_CACHE);
        $wpdb->delete($this->lookupTable(), ['product_or_parent_id' => self::PRODUCT_ID]);
    }

    if ($this->hostHandler instanceof ExceptionHandler) {
        $this->app->instance(ExceptionHandler::class, $this->hostHandler);
    }
});

it('names the attributes woocommerce marks as used for variations', function (): void {
    $names = new VariationTaxonomies()->all();

    expect($names)->toContain('pa_'.self::SIZE);
    expect($names)->not->toContain('pa_'.self::SKIN);
});

it('names every attribute while woocommerce does not use its lookup table', function (): void {
    $this->lookupEnabled = 'no';

    $names = new VariationTaxonomies()->all();

    expect($names)->toBe(array_values(wc_get_attribute_taxonomy_names()));
    expect($names)->toContain('pa_'.self::SKIN);
});

it('names every attribute while woocommerce rebuilds its lookup table', function (): void {
    $this->regenerating = 'yes';

    expect(new VariationTaxonomies()->all())->toContain('pa_'.self::SKIN);
});

it('reports a lookup table it cannot read and names every attribute', function (): void {
    $this->hostHandler = $this->app->make(ExceptionHandler::class);
    Exceptions::fake();
    $this->lookupEnabled = self::BROKEN;

    expect(new VariationTaxonomies()->all())->toBe(array_values(wc_get_attribute_taxonomy_names()));
    Exceptions::assertReported(RuntimeException::class);
});
