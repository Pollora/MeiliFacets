<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\IndexSetting;
use Modules\MeiliFacets\Enums\PaginationSetting;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\FacetedPostIndexable;
use Modules\MeiliFacets\Search\EngineLimits;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The ceiling the module reasons about and the one the engine enforces were two
 * values kept in step by hand. Now the module writes what it declares.
 *
 * Runs here rather than in the standalone suite: `getIndexSettings()` calls up
 * into MeiliScout, which reads WordPress options.
 */
final class IndexPaginationTest extends TestCase
{
    #[Test]
    public function it_writes_the_ceiling_it_declares(): void
    {
        $settings = $this->settingsFor(new EngineLimits(2500));

        $this->assertSame(
            2500,
            $settings[IndexSetting::Pagination->value][PaginationSetting::MaxTotalHits->value]
        );
    }

    #[Test]
    public function it_writes_the_engine_default_when_nothing_says_otherwise(): void
    {
        $settings = $this->settingsFor(new EngineLimits(EngineLimits::DEFAULT_REACHABLE_HITS));

        $this->assertSame(
            1000,
            $settings[IndexSetting::Pagination->value][PaginationSetting::MaxTotalHits->value]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsFor(EngineLimits $limits): array
    {
        return (new FacetedPostIndexable(new EmptyIndexAttributes, $limits))->getIndexSettings();
    }
}
