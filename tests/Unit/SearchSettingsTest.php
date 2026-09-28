<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\SiteSearch\SearchSettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchSettingsTest extends TestCase
{
    #[Test]
    public function it_waits_for_two_characters_and_120_ms_and_shows_four_results(): void
    {
        $settings = new SearchSettings;

        $this->assertSame([2, 120, 4], [$settings->minChars, $settings->delay, $settings->limit]);
    }

    #[Test]
    public function it_changes_one_setting_at_a_time(): void
    {
        $settings = new SearchSettings(3, 200, 6);

        $this->assertEquals(new SearchSettings(1, 200, 6), $settings->withMinChars(1));
        $this->assertEquals(new SearchSettings(3, 0, 6), $settings->withDelay(0));
        $this->assertEquals(new SearchSettings(3, 200, 6), $settings);
    }
}
