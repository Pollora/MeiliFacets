<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\FacetingSetting;
use Modules\MeiliFacets\Enums\IndexSetting;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use Modules\MeiliFacets\Search\EngineLimits;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Left to the engine, `maxValuesPerFacet` cut a distribution at 100 with nothing said.
 * Runs here rather than standalone: `getIndexSettings()` reads WordPress options.
 */
final class IndexFacetingTest extends TestCase
{
    #[Test]
    public function it_writes_the_facet_ceiling_it_declares(): void
    {
        $this->assertSame(2500, $this->facetingFor(new EngineLimits(1000, 2500))[FacetingSetting::MaxValuesPerFacet->value]);
    }

    #[Test]
    public function it_writes_its_own_default_rather_than_leaving_the_engine_its(): void
    {
        $written = $this->facetingFor(new EngineLimits(1000))[FacetingSetting::MaxValuesPerFacet->value];

        $this->assertSame(EngineLimits::DEFAULT_MAX_FACET_VALUES, $written);
        $this->assertNotSame(EngineLimits::ENGINE_MAX_FACET_VALUES, $written);
    }

    /**
     * @return array<string, mixed>
     */
    private function facetingFor(EngineLimits $limits): array
    {
        return new FacetedPostIndexable(new EmptyIndexAttributes, $limits)
            ->getIndexSettings()[IndexSetting::Faceting->value];
    }
}
