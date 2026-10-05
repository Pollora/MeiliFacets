<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\MeiliFacets\Http\ServiceUnavailable;
use Modules\MeiliFacets\Http\ServiceUnavailableHeaders;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tests\TestCase;

/** The status and the cache headers a page goes out with once its listing could not reach the engine. */
final class ServiceUnavailableHeadersTest extends TestCase
{
    #[Test]
    public function it_answers_503_uncached_once_a_listing_announced_the_outage(): void
    {
        $serviceUnavailable = new ServiceUnavailable;
        $serviceUnavailable->announce();

        $response = $this->through($serviceUnavailable, $this->publicPage());

        $this->assertSame(503, $response->getStatusCode());
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        $this->assertSame('120', $response->headers->get('Retry-After'));
        $this->assertFalse($response->headers->has('Expires'));
    }

    #[Test]
    public function it_leaves_a_page_alone_while_no_outage_was_announced(): void
    {
        $response = $this->through(new ServiceUnavailable, $this->publicPage());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    #[Test]
    public function it_runs_around_every_request(): void
    {
        $kernel = $this->app->make(HttpKernel::class);

        $this->assertTrue($kernel->hasMiddleware(ServiceUnavailableHeaders::class));
    }

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
