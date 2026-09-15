<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Search\EngineLimits;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EngineLimitsTest extends TestCase
{
    #[Test]
    public function it_reads_a_distribution_stopping_on_its_own_ceiling_as_cut(): void
    {
        $this->assertTrue(new EngineLimits(1000, 400)->looksTruncated(400));
    }

    /** Between a release and its reindexing, the index still caps where the engine does. */
    #[Test]
    public function it_reads_one_stopping_on_the_engine_default_as_cut_too(): void
    {
        $this->assertTrue(new EngineLimits(1000, 400)->looksTruncated(EngineLimits::ENGINE_MAX_FACET_VALUES));
    }

    #[Test]
    public function it_says_nothing_of_a_distribution_that_stops_on_neither(): void
    {
        $this->assertFalse(new EngineLimits(1000, 400)->looksTruncated(99));
        $this->assertFalse(new EngineLimits(1000, 400)->looksTruncated(399));
    }
}
