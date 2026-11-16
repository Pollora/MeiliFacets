<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Discovery\ListingDiscovery;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Infrastructure\Services\DiscoveryRegistrar;
use Tests\TestCase;

/** Pollora's registrar only adds a discovery the container binds under a name ending in `Discovery`. */
uses(TestCase::class);

it('pollora registers the listing discovery from the container', function (): void {
    $identifier = $this->app->make(ListingDiscovery::class)->getIdentifier();
    $engine = clone $this->app->make(DiscoveryEngineInterface::class);
    $engine->getDiscoveries()->forget($identifier);

    $this->app->make(DiscoveryRegistrar::class)->registerInto($engine);

    expect($engine->getDiscovery($identifier))->toBeInstanceOf(ListingDiscovery::class);
});
