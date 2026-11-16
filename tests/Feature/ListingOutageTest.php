<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Exceptions;
use Modules\MeiliFacets\Contracts\EngineUnavailable;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Http\ServiceUnavailable;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use Tests\TestCase;

trait ListingOutageSteps
{
    /** @var array{concrete: mixed, shared: bool} */
    private array $engine;

    private function failingEngine(): SearchEngine
    {
        return new class implements SearchEngine
        {
            public function multiSearch(array $queries): array
            {
                throw EngineUnavailable::unconfigured();
            }
        };
    }

    private function listingSearchingWith(SearchEngine $engine): ResolvedListing
    {
        return new ResolvedListing(
            new FakeListing([]),
            new ListingState,
            new ListingSearch($engine, new DisjunctiveFacetCounter),
            new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),

            new EngineLimits(EngineLimits::DEFAULT_REACHABLE_HITS, EngineLimits::DEFAULT_MAX_FACET_VALUES),
        );
    }
}

// Runs here rather than standalone: `report()` needs the application the Unit suite does without.
uses(TestCase::class, ListingOutageSteps::class);

beforeEach(function (): void {
    $this->engine = $this->app->getBindings()[SearchEngine::class];
    $this->app->forgetScopedInstances();
});

// The suite shares one application: a fake engine left bound would answer a later class.
afterEach(function (): void {
    $this->app->bind(SearchEngine::class, $this->engine['concrete'], $this->engine['shared']);
    $this->app->forgetScopedInstances();
});

// Varnish must not cache the outage message as the listing.
it('answers 503 when the listing of the page could not search', function (): void {
    Exceptions::fake();
    $this->app->instance(SearchEngine::class, $this->failingEngine());

    Blade::render('<x-meilifacets::listing name="products"></x-meilifacets::listing>');

    expect($this->app->make(ServiceUnavailable::class)->wasAnnounced())->toBeTrue();
});

it('announces nothing while the engine answers', function (): void {
    Blade::render('<x-meilifacets::listing name="products"></x-meilifacets::listing>');

    expect($this->app->make(ServiceUnavailable::class)->wasAnnounced())->toBeFalse();
});

it('reports an engine that fails the server render', function (): void {
    Exceptions::fake();

    $listing = $this->listingSearchingWith($this->failingEngine());

    expect($listing->failed())->toBeTrue();
    Exceptions::assertReported(EngineUnavailable::class);
});
