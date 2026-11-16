<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Indexing\DeferredCardProjector;
use Tests\TestCase;
use WP_Post;

trait DeferredCardProjectorSupport
{
    private bool $pluginIsActive = false;

    private function deferred(): DeferredCardProjector
    {
        return new DeferredCardProjector(
            fn (): bool => $this->pluginIsActive,
            $this->projecting('plugin'),
            $this->projecting('default')
        );
    }

    private function projecting(string $origin): CardProjector
    {
        return new readonly class($origin) implements CardProjector
        {
            public function __construct(private string $origin) {}

            /**
             * @return array<string, mixed>
             */
            public function project(WP_Post $post): array
            {
                return ['from' => $this->origin];
            }
        };
    }
}

uses(TestCase::class, DeferredCardProjectorSupport::class);

/** A hook wired before plugins load keeps its instance for the whole request (R-171). */
it('projects the plugin card once the plugin has loaded after the wiring', function (): void {
    $projector = $this->deferred();
    $this->pluginIsActive = true;

    expect($projector->project(new WP_Post((object) [])))->toBe(['from' => 'plugin']);
});

it('projects the default card while the plugin is absent', function (): void {
    expect($this->deferred()->project(new WP_Post((object) [])))->toBe(['from' => 'default']);
});
