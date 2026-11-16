<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\Tests\Unit\Doubles\AnsweredListing;
use Modules\MeiliFacets\View\Preconnect;
use ReflectionClass;
use Tests\TestCase;

trait PreconnectSupport
{
    private const string ENGINE = 'https://engine.example.test:7701';

    /** Built as the module builds it: the searched index is read on first use only. */
    private function connection(): BrowserConnection
    {
        return lazy(BrowserConnection::class, fn (): array => [self::ENGINE, 'search', 'posts']);
    }

    private function warm(Preconnect $preconnect): string
    {
        ob_start();
        $preconnect->warmTheEngineOrigin();

        return (string) ob_get_clean();
    }
}

uses(TestCase::class, PreconnectSupport::class);

it('leaves the connection unread on a page without a listing', function (): void {
    $connection = $this->connection();

    $printed = $this->warm(new Preconnect($connection, new ListingPage));

    expect($printed)->toBe('');
    expect(new ReflectionClass(BrowserConnection::class)->isUninitializedLazyObject($connection))->toBeTrue();
});

it('opens the engine origin on a listing page', function (): void {
    $page = new ListingPage;
    $page->markAsCurrent(AnsweredListing::withTotal());

    $printed = $this->warm(new Preconnect($this->connection(), $page));

    expect($printed)->toContain('rel="preconnect" href="'.self::ENGINE.'"');
});
