<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\MeiliFacets\Http\ServiceUnavailable;
use Modules\MeiliFacets\Http\ServiceUnavailableHeaders;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tests\TestCase;

trait ServiceUnavailableHeadersSupport
{
    private function through(ServiceUnavailable $serviceUnavailable, Response $page): SymfonyResponse
    {
        $middleware = new ServiceUnavailableHeaders($serviceUnavailable);

        return $middleware->handle(new Request, static fn (): Response => $page);
    }

    private function publicPage(): Response
    {
        $headers = ['Cache-Control' => 'public, max-age=3600', 'Expires' => 'Mon, 05 Oct 2026 12:00:00 GMT'];

        return new Response('page', 200, $headers);
    }
}

/** The status and the cache headers a page goes out with once its listing could not reach the engine. */
uses(TestCase::class, ServiceUnavailableHeadersSupport::class);

it('answers 503 uncached once a listing announced the outage', function (): void {
    $serviceUnavailable = new ServiceUnavailable;
    $serviceUnavailable->announce();

    $response = $this->through($serviceUnavailable, $this->publicPage());

    expect($response->getStatusCode())->toBe(503);
    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
    expect($response->headers->hasCacheControlDirective('public'))->toBeFalse();
    expect($response->headers->get('Retry-After'))->toBe('120');
    expect($response->headers->has('Expires'))->toBeFalse();
});

it('leaves a page alone while no outage was announced', function (): void {
    $response = $this->through(new ServiceUnavailable, $this->publicPage());

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->hasCacheControlDirective('public'))->toBeTrue();
});

it('runs around every request', function (): void {
    $kernel = $this->app->make(HttpKernel::class);

    expect($kernel->hasMiddleware(ServiceUnavailableHeaders::class))->toBeTrue();
});
