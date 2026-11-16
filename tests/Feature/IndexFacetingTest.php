<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\FacetingSetting;
use Modules\MeiliFacets\Enums\IndexSetting;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use Modules\MeiliFacets\Search\EngineLimits;
use Tests\TestCase;

trait IndexFacetingSupport
{
    /**
     * @return array<string, mixed>
     */
    private function facetingFor(EngineLimits $limits): array
    {
        return $this->settingsFor($limits)[IndexSetting::Faceting->value];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsFor(EngineLimits $limits): array
    {
        $attributes = ['attributes' => new EmptyIndexAttributes, 'limits' => $limits];

        return $this->app->make(FacetedPostIndexable::class, $attributes)->getIndexSettings();
    }
}

/**
 * Left to the engine, `maxValuesPerFacet` cut a distribution at 100 with nothing said.
 * Runs here rather than standalone: `getIndexSettings()` reads WordPress options.
 */
uses(TestCase::class, IndexFacetingSupport::class);

it('writes the facet ceiling it declares', function (): void {
    expect($this->facetingFor(new EngineLimits(1000, 2500))[FacetingSetting::MaxValuesPerFacet->value])->toBe(2500);
});

it('writes its own default rather than leaving the engine its', function (): void {
    $written = $this->facetingFor(new EngineLimits(1000))[FacetingSetting::MaxValuesPerFacet->value];

    expect($written)->toBe(EngineLimits::DEFAULT_MAX_FACET_VALUES);
    expect($written)->not->toBe(EngineLimits::ENGINE_MAX_FACET_VALUES);
});

/** MeiliScout removes the variant documents a save no longer writes by these two, with or without a plugin. */
it('filters on the id and the parent of a document without any plugin', function (): void {
    $filterable = $this->settingsFor(new EngineLimits(1000))[IndexSetting::FilterableAttributes->value];

    expect($filterable)->toContain('ID');
    expect($filterable)->toContain('parent_id');
});
