<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Support\UrlParameters;

/** Values of the host's real catalogue, written in the address as ticking them would: a catalogue too small skips. */
trait HoldsCatalogueValues
{
    private function holdTwoValues(): void
    {
        $slugs = $this->valuesOfTheFirstFacet(2);
        $parameter = $this->app->make(UrlParameters::class)->for($this->firstFacet()->taxonomy);

        request()->query->replace([$parameter => implode(',', $slugs)]);
        $this->app->forgetScopedInstances();
    }

    /**
     * @return list<string>
     */
    private function valuesOfTheFirstFacet(int $wanted): array
    {
        $values = $this->catalogue()->valuesOf($this->firstFacet());

        if (count($values) < $wanted) {
            $this->markTestSkipped("The first facet of the catalogue offers fewer than {$wanted} values.");
        }

        return array_map(static fn (FacetValue $value): string => $value->slug, array_slice($values, 0, $wanted));
    }

    private function firstFacet(): Facet
    {
        $facets = $this->catalogue()->facets();

        if ($facets === []) {
            $this->markTestSkipped('The catalogue declares no facet.');
        }

        return $facets[0];
    }

    private function catalogue(): ResolvedListing
    {
        return $this->app->make(CurrentListing::class)->sole();
    }
}
