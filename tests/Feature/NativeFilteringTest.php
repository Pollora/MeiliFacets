<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\ProductTaxonomy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WP_Query;
use WP_Term;

final class NativeFilteringTest extends TestCase
{
    private const string FILTER = 'woocommerce_enable_post_clause_filtering';

    /**
     * WooCommerce reads `min_price` and `filter_*` from `$_GET` and narrows the main
     * query in SQL. The engine answers that here, so the work is thrown away and
     * `found_posts` ends up counting a filter the page never rendered.
     */
    #[Test]
    public function it_stops_woocommerce_filtering_a_product_archive_in_parallel(): void
    {
        $this->assertFalse($this->askedWhileMain(['post_type' => 'product']));
    }

    /** The rollback `configuration.md` documents: one archive, after the module. */
    #[Test]
    public function it_lets_a_project_rearm_one_archive_and_that_one_only(): void
    {
        $categories = get_terms(['taxonomy' => ProductTaxonomy::Category->value, 'number' => 1, 'hide_empty' => false]);
        $term = $categories[0] ?? null;

        if (! $term instanceof WP_Term) {
            $this->markTestSkipped('The host has no product category to rearm.');
        }

        $rearm = static fn (bool $enabled, WP_Query $query): bool => $query->is_main_query()
            && $query->is_tax(ProductTaxonomy::Category->value) ? true : $enabled;
        add_filter(self::FILTER, $rearm, 20, 2);

        try {
            $this->assertTrue($this->askedWhileMain([ProductTaxonomy::Category->value => $term->slug]));
            $this->assertFalse($this->askedWhileMain(['post_type' => 'product']));
        } finally {
            remove_filter(self::FILTER, $rearm, 20);
        }
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function askedWhileMain(array $vars): bool
    {
        global $wp_the_query;

        $query = new WP_Query;
        $query->parse_query($vars);
        $main = $wp_the_query;
        $wp_the_query = $query;

        try {
            return (bool) apply_filters(self::FILTER, $query->is_main_query(), $query);
        } finally {
            $wp_the_query = $main;
        }
    }
}
