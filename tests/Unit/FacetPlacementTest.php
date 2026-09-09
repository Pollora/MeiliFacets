<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Http\Unavailable;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The rules a template plays by, on facets the test declares itself — the host
 * project must never be able to break the module's suite by adding a facet.
 */
final class FacetPlacementTest extends TestCase
{
    #[Test]
    public function it_offers_every_facet_when_a_template_places_none(): void
    {
        $this->assertSame(['brand', 'size'], $this->namesOf($this->listing()->remainingFacets()));
    }

    #[Test]
    public function it_drops_from_the_group_what_a_template_placed_apart(): void
    {
        $listing = $this->listing();

        $listing->placeApart($listing->facetNamed('brand'));

        $this->assertSame(['size'], $this->namesOf($listing->remainingFacets()));
    }

    /** Two identical blocks would duplicate the inputs and the ids they carry. */
    #[Test]
    public function it_refuses_to_render_the_same_facet_twice(): void
    {
        $listing = $this->listing();
        $brand = $listing->facetNamed('brand');

        $listing->place($brand);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Facet "brand" is rendered twice/');

        $listing->place($brand);
    }

    /** The group takes what is left, so placing after it places twice. */
    #[Test]
    public function it_refuses_a_facet_placed_after_the_group(): void
    {
        $listing = $this->listing();

        foreach ($listing->remainingFacets() as $facet) {
            $listing->place($facet);
        }

        $this->expectException(RuntimeException::class);

        $listing->placeApart($listing->facetNamed('brand'));
    }

    #[Test]
    public function it_names_the_facets_it_knows_when_asked_for_one_it_does_not(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No facet named "colour".*brand, size/');

        $this->listing()->facetNamed('colour');
    }

    /** A facet that names nothing answers to its taxonomy. */
    #[Test]
    public function it_finds_a_facet_that_was_never_named(): void
    {
        $listing = $this->listing([new Facet('product_tag', 'Tag')]);

        $this->assertSame('product_tag', $listing->facetNamed('product_tag')->name);
    }

    #[Test]
    public function it_refuses_two_facets_answering_to_the_same_name(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Facets "Aisles" and "Shelves" answer to the same name "product_cat"/');

        $this->listing([
            new Facet('product_cat', 'Aisles'),
            new Facet('product_cat', 'Shelves'),
        ])->facets();
    }

    #[Test]
    public function it_takes_two_facets_on_one_taxonomy_once_a_name_tells_them_apart(): void
    {
        $listing = $this->listing([
            new Facet('product_cat', 'Aisles', name: 'aisles'),
            new Facet('product_cat', 'Shelves'),
        ]);

        $this->assertSame(['aisles', 'product_cat'], $this->namesOf($listing->facets()));
    }

    /**
     * @param  list<Facet>|null  $facets
     */
    private function listing(?array $facets = null): ResolvedListing
    {
        return new ResolvedListing(
            new FakeListing($facets ?? [
                new Facet('product_brand', 'Brand', name: 'brand'),
                new Facet('pa_size', 'Size', name: 'size'),
            ]),
            new ListingState,
            new ListingSearch(new FakeSearchEngine, new DisjunctiveFacetCounter),
            new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
            new UrlParameters([]),
            new Unavailable,
            new EngineLimits(1000),
        );
    }

    /**
     * @param  list<Facet>  $facets
     * @return list<string>
     */
    private function namesOf(array $facets): array
    {
        return array_map(static fn (Facet $facet): string => $facet->name, $facets);
    }
}
