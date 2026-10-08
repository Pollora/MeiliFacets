<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Discovery\ListingDiscovery;
use PHPUnit\Framework\Attributes\Test;
use Pollora\Discovery\Domain\Contracts\DiscoveryEngineInterface;
use Pollora\Discovery\Infrastructure\Services\DiscoveryRegistrar;
use Tests\TestCase;

/** Pollora's registrar only adds a discovery the container binds under a name ending in `Discovery`. */
final class ListingDiscoveryRegistrationTest extends TestCase
{
    #[Test]
    public function pollora_registers_the_listing_discovery_from_the_container(): void
    {
        $identifier = $this->app->make(ListingDiscovery::class)->getIdentifier();
        $engine = clone $this->app->make(DiscoveryEngineInterface::class);
        $engine->getDiscoveries()->forget($identifier);

        $this->app->make(DiscoveryRegistrar::class)->registerFromContainer($engine);

        $this->assertInstanceOf(ListingDiscovery::class, $engine->getDiscovery($identifier));
    }
}
