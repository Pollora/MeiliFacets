<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Closure;
use Modules\MeiliFacets\Indexing\VariationChanges;
use Modules\MeiliFacets\Indexing\VariationMetaRewrites;
use PHPUnit\Framework\Attributes\Test;
use Pollora\MeiliScout\Providers\SingleIndexingServiceProvider;
use Tests\TestCase;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce;

final class VariationMetaRewritesTest extends TestCase
{
    use KeepsTheIndexOut;

    private const string ATTRIBUTE = 'meilifacets_test_size';

    private const string TAXONOMY = 'pa_'.self::ATTRIBUTE;

    private const string VARIATION_KEY = 'attribute_'.self::TAXONOMY;

    private const string SCHEDULE_INDEXATION = 'meiliscout/schedule_indexation';

    private const string SYNC_THRESHOLD = 'woocommerce_regenerate_variation_summaries_sync_threshold';

    private const string PRE_SCHEDULE_ACTION = 'pre_as_schedule_single_action';

    private const string PRE_SCHEDULE_EVENT = 'pre_schedule_event';

    private array $created = [];

    private array $categoryIds = [];

    private int $scheduled = 0;

    /** Each `$this->method(...)` is a new closure, which `remove_action()` would not find. */
    private Closure $scheduleRecorder;

    /** WooCommerce's summaries and MeiliScout's indexation would each write a scheduled event. */
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('Attributes are WooCommerce\'s.');
        }

        $this->registerAttribute();
        $this->scheduleRecorder = $this->recordSchedule(...);
        add_action(self::SCHEDULE_INDEXATION, $this->scheduleRecorder);
        // Below the threshold, WooCommerce's summaries clear the cache by chance.
        add_filter(self::SYNC_THRESHOLD, '__return_zero');
        add_filter(self::PRE_SCHEDULE_ACTION, '__return_zero');
        add_filter(self::PRE_SCHEDULE_EVENT, '__return_false');
    }

    protected function tearDown(): void
    {
        if (isset($this->scheduleRecorder)) {
            remove_action(self::SCHEDULE_INDEXATION, $this->scheduleRecorder);
        }

        remove_filter(self::SYNC_THRESHOLD, '__return_zero');
        remove_filter(self::PRE_SCHEDULE_ACTION, '__return_zero');
        remove_filter(self::PRE_SCHEDULE_EVENT, '__return_false');

        foreach (array_reverse($this->created) as $product) {
            $product->delete(true);
        }

        foreach ($this->categoryIds as $categoryId) {
            wp_delete_term($categoryId, 'product_cat');
        }

        $this->app->make(VariationChanges::class)->reindexChangedProducts();

        $this->unregisterAttribute();

        parent::tearDown();
    }

    #[Test]
    public function the_variation_and_its_product_hold_the_new_slug_at_meiliscout_priority(): void
    {
        $termId = $this->term('15ml');
        [$product, $variation] = $this->variable($termId, '15ml');
        get_post_meta($variation->get_id());
        get_post_meta($product->get_id());
        $read = [];
        $readAsMeiliScout = function () use ($product, $variation, &$read): void {
            $read = [
                get_post_meta($variation->get_id(), self::VARIATION_KEY, true),
                get_post_meta($product->get_id(), '_default_attributes', true),
            ];
        };

        add_action('edited_term', $readAsMeiliScout, SingleIndexingServiceProvider::EDITED_TERM_PRIORITY);

        try {
            wp_update_term($termId, self::TAXONOMY, ['slug' => '15-ml']);
        } finally {
            remove_action('edited_term', $readAsMeiliScout, SingleIndexingServiceProvider::EDITED_TERM_PRIORITY);
        }

        $this->assertSame(['15-ml', [self::TAXONOMY => '15-ml']], $read);
    }

    #[Test]
    public function a_term_outside_the_product_attributes_leaves_the_cache_alone(): void
    {
        [$product, $variation] = $this->variable($this->term('15ml'), '15ml');
        $category = wp_insert_term(uniqid('Meilifacets test category '), 'product_cat');
        $this->categoryIds[] = $category['term_id'];
        wp_set_object_terms($product->get_id(), [$category['term_id']], 'product_cat');
        get_post_meta($product->get_id());
        get_post_meta($variation->get_id());

        $this->rewrites()->clearMetaCacheOfTermProducts($category['term_id'], $category['term_taxonomy_id'], 'product_cat');

        $this->assertNotFalse(wp_cache_get($product->get_id(), 'post_meta'));
        $this->assertNotFalse(wp_cache_get($variation->get_id(), 'post_meta'));
    }

    #[Test]
    public function a_renamed_attribute_clears_its_variations_and_schedules_one_indexation(): void
    {
        [, $variation] = $this->variable($this->term('15ml'), '15ml');
        get_post_meta($variation->get_id());

        $this->rewrites()->rememberRenamedAttribute(1, ['attribute_name' => self::ATTRIBUTE], 'meilifacets_test_old');
        $this->rewrites()->rememberRenamedAttribute(1, ['attribute_name' => self::ATTRIBUTE], 'meilifacets_test_old');
        $this->rewrites()->reindexRenamedAttributes();

        $this->assertFalse(wp_cache_get($variation->get_id(), 'post_meta'));
        $this->assertSame(1, $this->scheduled);
    }

    #[Test]
    public function an_attribute_saved_under_its_own_slug_schedules_nothing(): void
    {
        $this->rewrites()->rememberRenamedAttribute(1, ['attribute_name' => self::ATTRIBUTE], self::ATTRIBUTE);
        $this->rewrites()->reindexRenamedAttributes();

        $this->assertSame(0, $this->scheduled);
    }

    private function recordSchedule(): void
    {
        $this->scheduled++;
    }

    private function rewrites(): VariationMetaRewrites
    {
        return $this->app->make(VariationMetaRewrites::class);
    }

    private function registerAttribute(): void
    {
        global $wc_product_attributes;

        register_taxonomy(self::TAXONOMY, 'product');
        $wc_product_attributes[self::TAXONOMY] = (object) ['attribute_name' => self::ATTRIBUTE];
    }

    private function unregisterAttribute(): void
    {
        global $wc_product_attributes;

        if (! taxonomy_exists(self::TAXONOMY)) {
            return;
        }

        foreach (get_terms(['taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'fields' => 'ids']) as $termId) {
            wp_delete_term($termId, self::TAXONOMY);
        }

        unset($wc_product_attributes[self::TAXONOMY]);
        unregister_taxonomy(self::TAXONOMY);
    }

    private function term(string $slug): int
    {
        return wp_insert_term($slug, self::TAXONOMY, ['slug' => $slug])['term_id'];
    }

    private function variable(int $termId, string $slug): array
    {
        $product = new WC_Product_Variable;
        $product->set_name('Variation meta rewrites, for the test');
        $product->set_default_attributes([self::TAXONOMY => $slug]);
        $product->save();
        wp_set_object_terms($product->get_id(), [$termId], self::TAXONOMY);
        $this->created[] = $product;

        $variation = new WC_Product_Variation;
        $variation->set_parent_id($product->get_id());
        $variation->set_attributes([self::TAXONOMY => $slug]);
        $variation->save();
        $this->created[] = $variation;

        return [$product, $variation];
    }
}
