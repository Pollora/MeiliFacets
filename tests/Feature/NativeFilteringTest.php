<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\ProductTaxonomy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WooCommerce;
use WP_Query;
use WP_Term;

final class NativeFilteringTest extends TestCase
{
    private const string FILTER = 'woocommerce_enable_post_clause_filtering';

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(WooCommerce::class)) {
            $this->markTestSkipped('The filter is WooCommerce\'s.');
        }
    }

    #[Test]
    public function it_stops_woocommerce_filtering_a_product_archive_in_parallel(): void
    {
        $this->assertFalse($this->answerAsMainQuery(['post_type' => 'product']));
    }

    #[Test]
    public function it_lets_a_project_rearm_one_archive_and_that_one_only(): void
    {
        [$rearmed, $other] = $this->twoCategories();
        $rearm = static fn (bool $enabled, WP_Query $query): bool => $enabled
            || ($query->is_main_query() && $query->is_tax(ProductTaxonomy::Category->value, $rearmed->slug));
        add_filter(self::FILTER, $rearm, 20, 2);

        try {
            $this->assertTrue($this->answerAsMainQuery([ProductTaxonomy::Category->value => $rearmed->slug]));
            $this->assertFalse($this->answerAsMainQuery([ProductTaxonomy::Category->value => $other->slug]));
            $this->assertFalse($this->answerAsMainQuery(['post_type' => 'product']));
        } finally {
            remove_filter(self::FILTER, $rearm, 20);
        }
    }

    /**
     * @return array{WP_Term, WP_Term}
     */
    private function twoCategories(): array
    {
        $categories = get_terms(['taxonomy' => ProductTaxonomy::Category->value, 'number' => 2, 'hide_empty' => false]);

        if (! is_array($categories) || count($categories) < 2) {
            $this->markTestSkipped('The host has fewer than two product categories.');
        }

        return [$categories[0], $categories[1]];
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function answerAsMainQuery(array $vars): bool
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
