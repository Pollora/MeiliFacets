<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\IndexSetting;
use Modules\MeiliFacets\Enums\PaginationSetting;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use Modules\MeiliFacets\Search\EngineLimits;
use Tests\TestCase;

trait IndexPaginationSteps
{
    /**
     * @return array<string, mixed>
     */
    private function settingsFor(EngineLimits $limits): array
    {
        $attributes = ['attributes' => new EmptyIndexAttributes, 'limits' => $limits];

        return $this->app->make(FacetedPostIndexable::class, $attributes)->getIndexSettings();
    }
}

// The ceiling the module reasons about and the one the engine enforces were two
// values kept in step by hand. Now the module writes what it declares.
//
// Runs here rather than in the standalone suite: `getIndexSettings()` calls up
// into MeiliScout, which reads WordPress options.
uses(TestCase::class, IndexPaginationSteps::class);

it('writes the ceiling it declares', function (): void {
    $settings = $this->settingsFor(new EngineLimits(2500));

    expect($settings[IndexSetting::Pagination->value][PaginationSetting::MaxTotalHits->value])->toBe(2500);
});

it('writes the engine default when nothing says otherwise', function (): void {
    $settings = $this->settingsFor(new EngineLimits(EngineLimits::DEFAULT_REACHABLE_HITS));

    expect($settings[IndexSetting::Pagination->value][PaginationSetting::MaxTotalHits->value])->toBe(1000);
});
