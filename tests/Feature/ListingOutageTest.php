<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Exceptions;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Http\ServiceUnavailable;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\EngineUnavailable;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Runs here rather than standalone: `report()` needs the application the Unit suite does without. */
final class ListingOutageTest extends TestCase
{
    /** @var array{concrete: mixed, shared: bool} */
    private array $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = $this->app->getBindings()[SearchEngine::class];
        $this->app->forgetScopedInstances();
    }

    /** The suite shares one application: a fake engine left bound would answer a later class. */
    protected function tearDown(): void
    {
        $this->app->bind(SearchEngine::class, $this->engine['concrete'], $this->engine['shared']);
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    /** Varnish must not cache the outage message as the listing. */
    #[Test]
    public function it_answers_503_when_the_listing_of_the_page_could_not_search(): void
    {
        Exceptions::fake();
        $this->app->instance(SearchEngine::class, $this->failingEngine());

        Blade::render('<x-meilifacets::listing name="products"></x-meilifacets::listing>');

        $this->assertTrue($this->app->make(ServiceUnavailable::class)->wasAnnounced());
    }

    #[Test]
    public function it_announces_nothing_while_the_engine_answers(): void
    {
        Blade::render('<x-meilifacets::listing name="products"></x-meilifacets::listing>');

        $this->assertFalse($this->app->make(ServiceUnavailable::class)->wasAnnounced());
    }

    #[Test]
    public function it_reports_an_engine_that_fails_the_server_render(): void
    {
        Exceptions::fake();

        $listing = $this->listingSearchingWith($this->failingEngine());

        $this->assertTrue($listing->failed());
        Exceptions::assertReported(EngineUnavailable::class);
    }

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
