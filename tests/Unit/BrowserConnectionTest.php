<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Search\BrowserConnection;

// A connection is opened per origin: the path the client posts to is not part of it.
it('keeps the port the engine answers on', function (): void {
    $connection = new BrowserConnection('https://example.test:7701/multi-search', 'key', 'posts');

    expect($connection->origin())->toBe('https://example.test:7701');
});

it('leaves out a port that was not given', function (): void {
    expect(new BrowserConnection('https://example.test', 'key', 'posts')->origin())->toBe('https://example.test');
});

/*
 * The origin is assembled from scheme, host and port rather than read off
 * `authority()`, which carries user credentials into the page.
 */
it('never carries credentials into the page', function (): void {
    $connection = new BrowserConnection('https://user:secret@engine.test:7700/', 'key', 'posts');

    expect($connection->origin())->toBe('https://engine.test:7700');
});

/*
 * A hostname pasted without its scheme reads as a path, so nothing can be
 * warmed and nothing can be queried. Guessing `https` would be a guess.
 */
it('refuses an address without a scheme', function (): void {
    $connection = new BrowserConnection('engine.example.test/', 'key', 'posts');

    expect($connection->origin())->toBe('');
    expect($connection->isConfigured())->toBeFalse();
});

it('has no origin to offer without an address', function (): void {
    expect(new BrowserConnection('', 'key', 'posts')->origin())->toBe('');
    expect(new BrowserConnection('nonsense', 'key', 'posts')->origin())->toBe('');
});

// Warming a connection to an engine no key can query is spent for nothing.
it('is configured only when all three are known', function (): void {
    expect(new BrowserConnection('https://e.test', 'key', 'posts')->isConfigured())->toBeTrue();
    expect(new BrowserConnection('', 'key', 'posts')->isConfigured())->toBeFalse();
    expect(new BrowserConnection('engine.test', 'key', 'posts')->isConfigured())->toBeFalse();
    expect(new BrowserConnection('https://e.test', '', 'posts')->isConfigured())->toBeFalse();
    expect(new BrowserConnection('https://e.test', 'key', '')->isConfigured())->toBeFalse();
});
