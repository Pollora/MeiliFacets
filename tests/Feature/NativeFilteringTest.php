<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NativeFilteringTest extends TestCase
{
    /**
     * WooCommerce reads `min_price` and `filter_*` from `$_GET` and narrows the main
     * query in SQL. The engine answers that here, so the work is thrown away and
     * `found_posts` ends up counting a filter the page never rendered.
     */
    #[Test]
    public function it_stops_woocommerce_filtering_the_main_query_in_parallel(): void
    {
        $this->assertFalse(apply_filters('woocommerce_enable_post_clause_filtering', true, null));
    }

    /** A project that wants the native filtering back says so after us. */
    #[Test]
    public function it_leaves_the_last_word_to_the_project(): void
    {
        add_filter('woocommerce_enable_post_clause_filtering', '__return_true', 20);

        $this->assertTrue(apply_filters('woocommerce_enable_post_clause_filtering', true, null));

        remove_filter('woocommerce_enable_post_clause_filtering', '__return_true', 20);
    }
}
