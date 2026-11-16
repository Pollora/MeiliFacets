<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\View\ElementId;

if (! function_exists(__NAMESPACE__.'\elementIdCombine')) {
    /** @return list<string> */
    function elementIdCombine(string $glue): array
    {
        $words = ['facet', 'panel', 'count', 'label', 'x'];
        $built = $words;

        for ($depth = 2; $depth <= 3; $depth++) {
            foreach ($built as $left) {
                foreach ($words as $right) {
                    $built[] = $left.$glue.$right;
                }
            }
        }

        return array_values(array_unique($built));
    }
}

if (! function_exists(__NAMESPACE__.'\elementIdHostileNames')) {
    /** @return list<string> */
    function elementIdHostileNames(): array
    {
        return array_values(array_unique(array_merge(elementIdCombine('-'), elementIdCombine('--'))));
    }
}

if (! function_exists(__NAMESPACE__.'\elementIdTermSlugs')) {
    /** @return list<string> */
    function elementIdTermSlugs(): array
    {
        return elementIdCombine('-');
    }
}

// Two listings on one page must not describe each other's controls.
it('carries the listing name', function (): void {
    $ids = new ElementId('products');

    expect($ids->sortTrigger())->toBe('meilifacets-products-sort-trigger');
    expect($ids->sortOption('newest'))->toBe('meilifacets-products-sort-option-newest');
    expect($ids->facetPanel('volume'))->toBe('meilifacets-products-facet-volume--panel');
    expect($ids->facetSelectedCount('volume'))->toBe('meilifacets-products-facet-volume--selected');
    expect($ids->facetCount('volume', '100ml'))->toBe('meilifacets-products-facet-volume--count-100ml');
    expect($ids->facetValueLabel('volume', '100ml'))->toBe('meilifacets-products-facet-volume--label-100ml');
    expect($ids->drawer())->toBe('meilifacets-products-drawer');
    expect($ids->drawerTitle())->toBe('meilifacets-products-drawer-title');
    expect($ids->drawerCount())->toBe('meilifacets-products-drawer-count');
});

// A header and a footer search on one page must not name each other's panel.
it('carries the search root name', function (): void {
    $ids = new ElementId('header');

    expect($ids->searchPanel())->toBe('meilifacets-header-search-panel');
    expect($ids->searchInput())->toBe('meilifacets-header-search-input');
    expect($ids->searchHeading('post'))->toBe('meilifacets-header-search-post-heading');
    expect($ids->searchListbox('post'))->toBe('meilifacets-header-search-post-results');
});

it('keeps two listings apart', function (): void {
    expect(new ElementId('products')->sortList())->not->toBe(new ElementId('articles')->sortList());
});

it('never builds one identifier from two different elements', function (): void {
    $ids = new ElementId('products');
    $source = [];

    foreach (elementIdHostileNames() as $facet) {
        $source[$ids->facetPanel($facet)][] = "panel({$facet})";
        $source[$ids->facetSelectedCount($facet)][] = "selectedCount({$facet})";

        foreach (elementIdTermSlugs() as $slug) {
            $source[$ids->facetCount($facet, $slug)][] = "count({$facet}, {$slug})";
            $source[$ids->facetValueLabel($facet, $slug)][] = "label({$facet}, {$slug})";
        }
    }

    foreach (elementIdHostileNames() as $key) {
        $source[$ids->sortOption($key)][] = "sortOption({$key})";
        $source[$ids->searchHeading($key)][] = "searchHeading({$key})";
        $source[$ids->searchListbox($key)][] = "searchListbox({$key})";
    }

    foreach ([$ids->searchPanel(), $ids->searchInput(), $ids->sortLabel(), $ids->sortTrigger(), $ids->sortList(), $ids->drawer(), $ids->drawerTitle(), $ids->drawerCount(), $ids->applyCount(), $ids->sortPanel(), $ids->sortChoiceName()] as $fixed) {
        $source[$fixed][] = 'fixed';
    }

    expect(array_filter($source, static fn (array $from): bool => count($from) > 1))->toBe([]);
});
