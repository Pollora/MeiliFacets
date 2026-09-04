<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListingSearchTest extends TestCase
{
    private const string RESULTS = 'results';

    private const string COUNT = 'count:';

    #[Test]
    public function it_sends_every_search_in_a_single_request(): void
    {
        $engine = new FakeSearchEngine;
        $state = new ListingState(['product_brand' => ['acme']]);

        $this->searchWith($engine)->run(FakeListing::withBrandAndCategory(), $state);

        $this->assertSame(1, $engine->calls);
        $this->assertSame([self::RESULTS, self::COUNT.'product_brand'], array_keys($engine->received[0]));
    }

    #[Test]
    public function it_reads_the_cards_of_the_main_response(): void
    {
        $engine = new FakeSearchEngine([self::RESULTS => [
            'hits' => [['card' => ['title' => 'Coat']], ['card' => ['title' => 'Scarf']], ['no_card' => true]],
            'estimatedTotalHits' => 27,
        ]]);

        $results = $this->searchWith($engine)->run(FakeListing::withBrandAndCategory(), new ListingState);

        $this->assertSame(27, $results->total);
        $this->assertSame([['title' => 'Coat'], ['title' => 'Scarf']], $results->cards());
    }

    #[Test]
    public function it_reads_a_constrained_facet_from_its_own_response(): void
    {
        $state = new ListingState(['product_brand' => ['acme']]);
        $engine = new FakeSearchEngine([
            self::RESULTS => ['facetDistribution' => ['facets.product_cat' => ['coats' => 2]]],
            self::COUNT.'product_brand' => [
                'facetDistribution' => ['facets.product_brand' => ['acme' => 2, 'globex' => 5]],
            ],
        ]);

        $results = $this->searchWith($engine)->run(FakeListing::withBrandAndCategory(), $state);

        $this->assertSame(['acme' => 2, 'globex' => 5], $results->distribution('product_brand'));
        $this->assertSame(['coats' => 2], $results->distribution('product_cat'));
    }

    #[Test]
    public function it_survives_an_engine_answering_nothing(): void
    {
        $results = $this->searchWith(new FakeSearchEngine)->run(FakeListing::withBrandAndCategory(), new ListingState);

        $this->assertSame(0, $results->total);
        $this->assertSame([], $results->cards());
        $this->assertSame([], $results->distribution('product_brand'));
    }

    private function searchWith(FakeSearchEngine $engine): ListingSearch
    {
        return new ListingSearch($engine, new DisjunctiveFacetCounter);
    }
}
