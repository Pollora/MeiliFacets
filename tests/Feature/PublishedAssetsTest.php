<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Support\PublishedAssets;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PublishedAssetsTest extends TestCase
{
    private const string PROBE = 'modules/meilifacets/css/meilifacets.css';

    private const string SOURCE = 'resources/assets/css/meilifacets.css';

    #[Test]
    public function it_reports_nothing_while_every_copy_is_current(): void
    {
        $this->assertSame([], new PublishedAssets()->stale());
    }

    /** A copy older than its source leaves the browser reading rules nobody holds. */
    #[Test]
    public function it_names_a_copy_left_behind_by_its_source(): void
    {
        $published = public_path(self::PROBE);
        $was = filemtime($published);

        // Dated against the source: a fresh publish leaves every copy newer than everything.
        touch($published, filemtime(module_path('MeiliFacets', self::SOURCE)) - 60);
        clearstatcache(true, $published);

        $this->assertContains('css/meilifacets.css', new PublishedAssets()->stale());

        touch($published, $was);
    }

    #[Test]
    public function it_names_a_copy_that_was_never_made(): void
    {
        $published = public_path(self::PROBE);
        $kept = (string) file_get_contents($published);
        $was = filemtime($published);
        unlink($published);

        $this->assertContains('css/meilifacets.css', new PublishedAssets()->stale());

        file_put_contents($published, $kept);
        touch($published, $was);
    }
}
