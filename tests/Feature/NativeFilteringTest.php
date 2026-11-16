<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\ProductTaxonomy;
use Tests\TestCase;
use WooCommerce;
use WP_Query;
use WP_Term;

trait NativeFilteringSteps
{
    private const string FILTER = 'woocommerce_enable_post_clause_filtering';

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

uses(TestCase::class, NativeFilteringSteps::class);

beforeEach(function (): void {
    if (! class_exists(WooCommerce::class)) {
        $this->markTestSkipped('The filter is WooCommerce\'s.');
    }
});

it('stops woocommerce filtering a product archive in parallel', function (): void {
    expect($this->answerAsMainQuery(['post_type' => 'product']))->toBeFalse();
});

it('lets a project rearm one archive and that one only', function (): void {
    [$rearmed, $other] = $this->twoCategories();
    $rearm = static fn (bool $enabled, WP_Query $query): bool => $enabled
        || ($query->is_main_query() && $query->is_tax(ProductTaxonomy::Category->value, $rearmed->slug));
    add_filter(self::FILTER, $rearm, 20, 2);

    try {
        expect($this->answerAsMainQuery([ProductTaxonomy::Category->value => $rearmed->slug]))->toBeTrue();
        expect($this->answerAsMainQuery([ProductTaxonomy::Category->value => $other->slug]))->toBeFalse();
        expect($this->answerAsMainQuery(['post_type' => 'product']))->toBeFalse();
    } finally {
        remove_filter(self::FILTER, $rearm, 20);
    }
});
