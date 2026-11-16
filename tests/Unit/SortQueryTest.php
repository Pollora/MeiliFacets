<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\SortFilter;
use Modules\MeiliFacets\Search\SortQuery;

beforeEach(function (): void {
    $this->query = new SortQuery([
        'on_sale' => SortFilter::whereTrue('price.onsale'),
        'in_stock' => SortFilter::whereTrue('in_stock'),
        'sale_only' => new SortFilter('price.onsale', 'yes'),
    ]);
});

it('lists each filtered field once', function (): void {
    expect($this->query->fields())->toBe(['price.onsale', 'in_stock']);
});

it('filters on the field the chosen sort carries', function (): void {
    expect($this->query->clause(new ListingState(sort: 'in_stock')))->toBe('in_stock = "true"');
});

it('adds no clause when no sort is chosen', function (): void {
    expect($this->query->clause(new ListingState))->toBe('');
});

it('adds no clause for a sort that filters nothing', function (): void {
    expect($this->query->clause(new ListingState(sort: 'price_asc')))->toBe('');
});

it('counts what each filtering sort would keep', function (): void {
    $distribution = ['price.onsale' => ['true' => 4, 'false' => 9], 'in_stock' => ['true' => 11]];

    expect($this->query->matchesIn($distribution))->toBe(['on_sale' => 4, 'in_stock' => 11, 'sale_only' => 0]);
});

it('has nothing to filter or count without a filtering sort', function (): void {
    $query = new SortQuery([]);

    expect($query->fields())->toBe([]);
    expect($query->matchesIn(['in_stock' => ['true' => 3]]))->toBe([]);
    expect($query->clause(new ListingState(sort: 'in_stock')))->toBe('');
});
