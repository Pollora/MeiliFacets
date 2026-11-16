<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\SortChoices;

use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Listing\SortFilter;
use Modules\MeiliFacets\View\ElementId;
use Modules\MeiliFacets\View\SortChoices;

beforeEach(function (): void {
    $this->choices = new SortChoices(new ElementId('products'));
});

/**
 * @return array<string, Sort>
 */
function withPromotions(): array
{
    return [...sorts(), 'on_sale' => Sort::filtering('On sale', SortFilter::whereTrue('price.onsale'))];
}

/**
 * @return array<string, Sort>
 */
function sorts(): array
{
    return [
        'price_asc' => new Sort('Price, low to high', ['metas._price:asc']),
        'newest' => new Sort('New arrivals', ['post_date:desc']),
    ];
}

/** Without it, no way back to the engine's own order once a sort is picked. */
it('opens with the default order', function (): void {
    $first = $this->choices->of(sorts(), null, [])[0];

    expect($first->value)->toBe('');
    expect($first->selected)->toBeTrue();
});

it('keeps the order the listing declared', function (): void {
    $values = array_column($this->choices->of(sorts(), null, []), 'value');

    expect($values)->toBe(['', 'price_asc', 'newest']);
});

it('marks the current sort and no other', function (): void {
    $selected = array_filter($this->choices->of(sorts(), 'newest', []), fn ($choice) => $choice->selected);

    expect($selected)->toHaveCount(1);
    expect(array_values($selected)[0]->value)->toBe('newest');
});

/** aria-activedescendant needs an id per option, unique across listings. */
it('gives every choice an identifier', function (): void {
    $ids = array_column($this->choices->of(sorts(), null, []), 'id');

    expect($ids)->toBe([
        'meilifacets-products-sort-option-default',
        'meilifacets-products-sort-option-price_asc',
        'meilifacets-products-sort-option-newest',
    ]);
});

it('hides a filtering sort that would keep nothing', function (): void {
    $choices = $this->choices->of(withPromotions(), null, ['on_sale' => 0]);

    expect(array_column($choices, 'hidden', 'value')['on_sale'])->toBeTrue();
});

/** Hidden, not removed: the client shows it again, under its own words and identifier, once it keeps something. */
it('keeps the words and identifier of a hidden choice', function (): void {
    $choices = $this->choices->of(withPromotions(), null, ['on_sale' => 0]);
    $hidden = array_column($choices, null, 'value')['on_sale'];

    expect($hidden->label)->toBe('On sale');
    expect($hidden->id)->toBe(new ElementId('products')->sortOption('on_sale'));
    expect($hidden->selected)->toBeFalse();
});

it('shows a filtering sort that keeps something', function (): void {
    $choices = $this->choices->of(withPromotions(), null, ['on_sale' => 3]);

    expect(array_column($choices, 'hidden', 'value')['on_sale'])->toBeFalse();
});

it('never hides the sort in use', function (): void {
    $choices = $this->choices->of(withPromotions(), 'on_sale', ['on_sale' => 0]);

    expect(array_column($choices, 'hidden', 'value')['on_sale'])->toBeFalse();
});

it('never hides a sort that only orders', function (): void {
    $hidden = array_column($this->choices->of(withPromotions(), null, []), 'hidden', 'value');

    expect(array_diff_key($hidden, ['on_sale' => true]))->toBe(['' => false, 'price_asc' => false, 'newest' => false]);
});
