<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Search\BrowserConnection;
use Modules\MeiliFacets\View\Preconnect;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

final class PreconnectTest extends TestCase
{
    private const string ENGINE = 'https://engine.example.test:7701';

    #[Test]
    public function it_leaves_the_connection_unread_on_a_page_without_a_listing(): void
    {
        $connection = $this->connection();

        $printed = $this->warm(new Preconnect($connection, new ListingPage));

        $this->assertSame('', $printed);
        $this->assertTrue(new ReflectionClass(BrowserConnection::class)->isUninitializedLazyObject($connection));
    }

    #[Test]
    public function it_opens_the_engine_origin_on_a_listing_page(): void
    {
        $page = new ListingPage;
        $page->markAsCurrent();

        $printed = $this->warm(new Preconnect($this->connection(), $page));

        $this->assertStringContainsString('rel="preconnect" href="'.self::ENGINE.'"', $printed);
    }

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
