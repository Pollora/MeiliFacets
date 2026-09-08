<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Search\BrowserConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BrowserConnectionTest extends TestCase
{
    /** A connection is opened per origin: the path the client posts to is not part of it. */
    #[Test]
    public function it_keeps_the_port_the_engine_answers_on(): void
    {
        $connection = new BrowserConnection('https://example.test:7701/multi-search', 'key', 'posts');

        $this->assertSame('https://example.test:7701', $connection->origin());
    }

    #[Test]
    public function it_leaves_out_a_port_that_was_not_given(): void
    {
        $this->assertSame(
            'https://example.test',
            new BrowserConnection('https://example.test', 'key', 'posts')->origin()
        );
    }

    /**
     * The origin is assembled from scheme, host and port rather than read off
     * `authority()`, which carries user credentials into the page.
     */
    #[Test]
    public function it_never_carries_credentials_into_the_page(): void
    {
        $connection = new BrowserConnection('https://user:secret@engine.test:7700/', 'key', 'posts');

        $this->assertSame('https://engine.test:7700', $connection->origin());
    }

    /**
     * A hostname pasted without its scheme reads as a path, so nothing can be
     * warmed and nothing can be queried. Guessing `https` would be a guess.
     */
    #[Test]
    public function it_refuses_an_address_without_a_scheme(): void
    {
        $connection = new BrowserConnection('engine.cleverapps.io/', 'key', 'posts');

        $this->assertSame('', $connection->origin());
        $this->assertFalse($connection->isConfigured());
    }

    #[Test]
    public function it_has_no_origin_to_offer_without_an_address(): void
    {
        $this->assertSame('', new BrowserConnection('', 'key', 'posts')->origin());
        $this->assertSame('', new BrowserConnection('nonsense', 'key', 'posts')->origin());
    }

    /** Warming a connection to an engine no key can query is spent for nothing. */
    #[Test]
    public function it_is_configured_only_when_all_three_are_known(): void
    {
        $this->assertTrue(new BrowserConnection('https://e.test', 'key', 'posts')->isConfigured());
        $this->assertFalse(new BrowserConnection('', 'key', 'posts')->isConfigured());
        $this->assertFalse(new BrowserConnection('engine.test', 'key', 'posts')->isConfigured());
        $this->assertFalse(new BrowserConnection('https://e.test', '', 'posts')->isConfigured());
        $this->assertFalse(new BrowserConnection('https://e.test', 'key', '')->isConfigured());
    }
}
