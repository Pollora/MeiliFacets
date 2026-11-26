<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\ResolvedListingReadsOnce;

use Modules\MeiliFacets\Contracts\CardComponentListing;
use Modules\MeiliFacets\Contracts\DefaultSortedListing;
use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Contracts\VariantScopedListing;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\SearchScope;
use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Listing\SortFilter;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultSortedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeVariantScopedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;

beforeEach(function (): void {
    WordPress::boot();
});

function resolve(
    Listing $listing,
    FakeSearchEngine $engine = new FakeSearchEngine,
    ?ListingState $state = null,
    FakeTermLabels $labels = new FakeTermLabels,
    int $maxFacetValues = 1000,
): ResolvedListing {
    return new ResolvedListing(
        $listing,
        $state ?? new ListingState,
        new ListingSearch($engine, new DisjunctiveFacetCounter),
        new FacetValues($labels, new FakeTermScope, new FakeDefaultTerms),
        new EngineLimits(1000, $maxFacetValues),
    );
}

/** A listing whose declarations count how often they are asked for: a project's may query the database in them. */
function countingListing(): Listing
{
    return new class(new FakeListing([new Facet('product_brand', 'Brand')])) implements Listing
    {
        /** @var array<string, int> */
        public array $asked = ['filters' => 0, 'sorts' => 0];

        public function __construct(private readonly FakeListing $listing) {}

        public function name(): string
        {
            return $this->listing->name();
        }

        public function facets(): array
        {
            return $this->listing->facets();
        }

        public function filters(): array
        {
            $this->asked['filters']++;

            return $this->listing->filters();
        }

        public function sorts(): array
        {
            $this->asked['sorts']++;

            return $this->listing->sorts();
        }

        public function baseFilter(): array
        {
            return [];
        }

        public function baseQuery(): string
        {
            return '';
        }

        public function perPage(): int
        {
            return 16;
        }

        public function applyMode(): ApplyMode
        {
            return ApplyMode::OnSubmit;
        }
    };
}

it('searches the engine and names the values of a facet once, however often the page reads them', function (): void {
    $engine = new FakeSearchEngine(['results' => ['totalHits' => 40, 'facetDistribution' => ['facets.product_brand' => ['acme' => 2]]]]);
    $labels = new FakeTermLabels(['acme' => 'Acme']);
    $facet = new Facet('product_brand', 'Brand');
    $resolved = resolve(new FakeListing([$facet]), $engine, labels: $labels);

    $resolved->failed();
    $resolved->cards();
    $resolved->total();
    $resolved->valuesOf($facet);
    $resolved->valuesOf($facet);

    expect($engine->calls)->toBe(1)
        ->and($labels->lookups)->toBe(1)
        ->and($resolved->pagination())->toBe($resolved->pagination());
});

it('asks the listing for its filters and sorts once', function (): void {
    $listing = countingListing();
    $resolved = resolve($listing);

    $resolved->filters();
    $resolved->filters();
    $before = $listing->asked['sorts'];
    $resolved->sorts();
    $resolved->sorts();

    expect($listing->asked['filters'])->toBe(1)
        ->and($listing->asked['sorts'] - $before)->toBe(1);
});

it('reports a facet cut by the engine even when only the unfiltered count reaches the ceiling', function (): void {
    $facet = new Facet('product_brand', 'Brand');
    $engine = new FakeSearchEngine([
        'results' => ['facetDistribution' => ['facets.product_brand' => ['acme' => 1]]],
        'unfiltered' => ['facetDistribution' => ['facets.product_brand' => ['acme' => 1, 'globex' => 1, 'initech' => 1]]],
    ]);

    resolve(new FakeListing([$facet]), $engine, new ListingState(query: 'a'), maxFacetValues: 3)->valuesOf($facet);

    expect(WordPress::$calls['report'] ?? 0)->toBe(1);
});

it('shows no filter of a kind it cannot read, narrowed or not', function (): void {
    $unknown = new class implements Placeable
    {
        public string $name = 'other';

        public string $label = 'Other';
    };

    $resolved = resolve(new FakeListing([$unknown]));
    $narrowed = resolve(new FakeListing([$unknown]), state: new ListingState(query: 'a'));

    expect($resolved->isShown($unknown))->toBeFalse()
        ->and($narrowed->placement()->isBeyondRow($unknown))->toBeFalse()
        ->and($narrowed->isShown($unknown))->toBeFalse();
});

it('gives the card component and the default order a listing declares', function (): void {
    $listing = new FakeDefaultSortedListing(new FakeListing, [3 => 'menu_order:asc']);

    expect(resolve($listing)->defaultSort())->toBe(['menu_order:asc'])
        ->and(resolve(new FakeListing)->defaultSort())->toBe([])
        ->and(resolve(new FakeListing)->cardComponent())->toBeNull()
        ->and(resolve(componentListing())->cardComponent())->toBe('cards.wine');
});

function componentListing(): CardComponentListing
{
    return new class implements CardComponentListing
    {
        public function cardComponent(): string
        {
            return 'cards.wine';
        }

        public function name(): string
        {
            return 'wine';
        }

        public function facets(): array
        {
            return [];
        }

        public function filters(): array
        {
            return [];
        }

        public function sorts(): array
        {
            return [];
        }

        public function baseFilter(): array
        {
            return [];
        }

        public function baseQuery(): string
        {
            return '';
        }

        public function perPage(): int
        {
            return 16;
        }

        public function applyMode(): ApplyMode
        {
            return ApplyMode::OnSubmit;
        }
    };
}

it('gives how many hits each filtering sort would keep', function (): void {
    $listing = FakeListing::withPromotions();
    $engine = new FakeSearchEngine(['results' => ['facetDistribution' => ['price.onsale' => ['true' => 4]]]]);

    expect(resolve($listing, $engine)->sortMatches())->toBe(['on_sale' => 4]);
});

/** Variable products on sale in one size only must show that size when the visitor asks for promotions. */
it('shows a card through a variant the filtering sort keeps', function (): void {
    $variants = [
        ['facets' => ['pa_size' => ['m']], 'price' => 5.0, 'fields' => ['url' => '/full-price'], 'on_sale' => false],
        ['facets' => ['pa_size' => ['m']], 'price' => 9.0, 'fields' => ['url' => '/on-sale'], 'on_sale' => true],
    ];
    $engine = new FakeSearchEngine(['results' => ['hits' => [['card' => ['url' => '/product', 'variants' => $variants]]]]]);

    $resolved = resolve(promotedSizes(), $engine, new ListingState(['pa_size' => ['m']], sort: 'on_sale'));

    expect($resolved->cards()[0]['url'])->toBe('/on-sale');
});

function promotedSizes(): VariantScopedListing&DefaultSortedListing
{
    return new readonly class(new FakeVariantScopedListing) implements DefaultSortedListing, VariantScopedListing
    {
        public function __construct(private FakeVariantScopedListing $listing) {}

        public function sorts(): array
        {
            return ['on_sale' => Sort::filtering('On sale', SortFilter::whereTrue('price.onsale'))];
        }

        public function name(): string
        {
            return $this->listing->name();
        }

        public function facets(): array
        {
            return $this->listing->facets();
        }

        public function filters(): array
        {
            return $this->listing->filters();
        }

        public function baseFilter(): array
        {
            return $this->listing->baseFilter();
        }

        public function baseQuery(): string
        {
            return $this->listing->baseQuery();
        }

        public function perPage(): int
        {
            return $this->listing->perPage();
        }

        public function applyMode(): ApplyMode
        {
            return $this->listing->applyMode();
        }

        public function defaultSort(): array
        {
            return $this->listing->defaultSort();
        }

        public function variantTaxonomies(): array
        {
            return $this->listing->variantTaxonomies();
        }

        public function variantFilter(): array
        {
            return $this->listing->variantFilter();
        }

        public function searchScope(): SearchScope
        {
            return $this->listing->searchScope();
        }

        public function variantSearchScope(): SearchScope
        {
            return $this->listing->variantSearchScope();
        }
    };
}
