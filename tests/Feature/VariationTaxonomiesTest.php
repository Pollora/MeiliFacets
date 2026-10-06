<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Automattic\WooCommerce\Internal\ProductAttributesLookup\LookupDataStore;
use Closure;
use Modules\MeiliFacets\Listing\VariationTaxonomies;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;
use WC_Cache_Helper;
use WooCommerce;

final class VariationTaxonomiesTest extends TestCase
{
    private const string SIZE = 'meilifacets_test_size';

    private const string SKIN = 'meilifacets_test_skin';

    private const string ATTRIBUTES = 'woocommerce_attribute_taxonomies';

    private const string LOOKUP_ENABLED = 'pre_option_woocommerce_attribute_lookup_enabled';

    private const string REGENERATING = 'pre_option_woocommerce_attribute_lookup_regeneration_in_progress';

    private const string ATTRIBUTE_CACHE = 'woocommerce-attributes';

    /** Far above any real post: the rows are told apart from the shop's by it. */
    private const int PRODUCT_ID = 2_000_000_001;

    private const int ATTRIBUTE_ID_BASE = 2_000_000_000;

    /** Each `$this->method(...)` is a new closure, which `remove_filter()` would not find. */
    private Closure $attributes;

    private Closure $lookupEnabledAnswer;

    private Closure $regeneratingAnswer;

    private string $lookupEnabled = 'yes';

    private string $regenerating = 'no';

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('Attributes are WooCommerce\'s.');
        }

        $this->attributes = $this->withTestAttributes(...);
        $this->lookupEnabledAnswer = fn (): string => $this->lookupEnabled;
        $this->regeneratingAnswer = fn (): string => $this->regenerating;
        add_filter(self::ATTRIBUTES, $this->attributes);
        add_filter(self::LOOKUP_ENABLED, $this->lookupEnabledAnswer);
        add_filter(self::REGENERATING, $this->regeneratingAnswer);
        WC_Cache_Helper::invalidate_cache_group(self::ATTRIBUTE_CACHE);
        $this->insertLookupRow(self::SIZE, isVariation: true);
        $this->insertLookupRow(self::SKIN, isVariation: false);
    }

    protected function tearDown(): void
    {
        global $wpdb;

        if (isset($this->attributes)) {
            remove_filter(self::ATTRIBUTES, $this->attributes);
            remove_filter(self::LOOKUP_ENABLED, $this->lookupEnabledAnswer);
            remove_filter(self::REGENERATING, $this->regeneratingAnswer);
            WC_Cache_Helper::invalidate_cache_group(self::ATTRIBUTE_CACHE);
            $wpdb->delete($this->lookupTable(), ['product_or_parent_id' => self::PRODUCT_ID]);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_names_the_attributes_woocommerce_marks_as_used_for_variations(): void
    {
        $names = new VariationTaxonomies()->all();

        $this->assertContains('pa_'.self::SIZE, $names);
        $this->assertNotContains('pa_'.self::SKIN, $names);
    }

    #[Test]
    public function it_names_every_attribute_while_woocommerce_does_not_use_its_lookup_table(): void
    {
        $this->lookupEnabled = 'no';

        $names = new VariationTaxonomies()->all();

        $this->assertSame(array_values(wc_get_attribute_taxonomy_names()), $names);
        $this->assertContains('pa_'.self::SKIN, $names);
    }

    #[Test]
    public function it_names_every_attribute_while_woocommerce_rebuilds_its_lookup_table(): void
    {
        $this->regenerating = 'yes';

        $this->assertContains('pa_'.self::SKIN, new VariationTaxonomies()->all());
    }

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
