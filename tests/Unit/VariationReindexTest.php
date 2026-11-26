<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\VariationReindex;

use Modules\MeiliFacets\Indexing\WooCommerce\VariationChanges;
use Modules\MeiliFacets\Indexing\WooCommerce\VariationMetaRewrites;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;

beforeEach(function (): void {
    WordPress::boot();
    WordPress::bootWooCommerce();
    WordPress::fake('do_action', null);
    WordPress::fake('clean_post_cache', null);
    WordPress::fake('wc_attribute_taxonomy_name', static fn (string $name): string => 'pa_'.$name);
    WordPress::fake('wc_variation_attribute_name', static fn (string $taxonomy): string => 'attribute_'.$taxonomy);
});

/**
 * @return list<array<int, mixed>>
 */
function argumentsOf(string $function): array
{
    return array_values(array_column(
        array_filter(WordPress::$log, static fn (array $call): bool => $call[0] === $function),
        1,
    ));
}

it('reindexes the product of the variation whose meta changed, and no other post', function (): void {
    // Post 1 is a variation of product 70; post 5 is not a variation, and post 0 names none.
    WordPress::fake('get_post_type', static fn (int $id): string => $id === 5 ? 'post' : 'product_variation');
    WordPress::fake('wp_get_post_parent_id', static fn (int $id): int => $id === 1 ? 1 : $id * 10);
    $changes = new VariationChanges;

    $changes->rememberProductOfVariationMeta(1, 0);
    $changes->rememberProductOfVariationMeta(1, 5);
    $changes->rememberProductOfVariationMeta([1, 2], 1);
    $changes->reindexChangedProducts();

    expect(argumentsOf('do_action'))->toBe([['meiliscout/reindex_post', 1]]);
});

it('searches every variation still carrying the renamed attribute, whatever its status', function (): void {
    WordPress::fake('get_posts', [40]);
    $rewrites = new VariationMetaRewrites;

    $rewrites->rememberRenamedAttribute(1, ['attribute_name' => 'colour'], 'color');
    $rewrites->reindexRenamedAttributes();

    expect(argumentsOf('get_posts'))->toBe([[[
        'meta_key' => 'attribute_pa_colour',
        'post_type' => 'product_variation',
        'post_status' => 'any',
        'nopaging' => true,
        'fields' => 'ids',
    ]]])->and(argumentsOf('do_action'))->toBe([['meiliscout/schedule_indexation']]);
});

it('does not reindex the shop when an attribute is saved without being renamed', function (): void {
    $rewrites = new VariationMetaRewrites;

    $rewrites->rememberRenamedAttribute(1, ['attribute_name' => 'colour'], 'colour');
    $rewrites->reindexRenamedAttributes();

    expect(argumentsOf('do_action'))->toBe([]);
});
