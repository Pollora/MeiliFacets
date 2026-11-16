<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\File;
use Modules\MeiliFacets\Support\PublishedAssets;
use Tests\TestCase;

trait PublishedAssetsSteps
{
    private const string PROBE = 'modules/meilifacets/css/meilifacets.css';

    private const string SOURCE = 'resources/assets/css/meilifacets.css';
}

uses(TestCase::class, PublishedAssetsSteps::class);

it('reports nothing while every copy is current', function (): void {
    expect(new PublishedAssets()->stale())->toBe([]);
});

// A copy older than its source leaves the browser reading rules nobody holds.
it('names a copy left behind by its source', function (): void {
    $published = public_path(self::PROBE);
    $was = filemtime($published);

    // Dated against the source: a fresh publish leaves every copy newer than everything.
    touch($published, filemtime(module_path('MeiliFacets', self::SOURCE)) - 60);
    clearstatcache(true, $published);

    expect(new PublishedAssets()->stale())->toContain('css/meilifacets.css');

    touch($published, $was);
});

it('names a copy that was never made', function (): void {
    $published = public_path(self::PROBE);
    $kept = (string) file_get_contents($published);
    $was = filemtime($published);
    File::delete($published);

    expect(new PublishedAssets()->stale())->toContain('css/meilifacets.css');

    file_put_contents($published, $kept);
    touch($published, $was);
});
