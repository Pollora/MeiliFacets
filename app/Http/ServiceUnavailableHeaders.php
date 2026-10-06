<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pollora answers every page 200 and applies its own cache headers: a global middleware has the last word on both.
 */
final readonly class ServiceUnavailableHeaders
{
    public function __construct(private ServiceUnavailable $serviceUnavailable) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->serviceUnavailable->wasAnnounced()) {
            $this->serviceUnavailable->applyTo($response);
        }

        return $response;
    }
}
