<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Indexing\DeferredCardProjector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WP_Post;

final class DeferredCardProjectorTest extends TestCase
{
    private bool $pluginIsActive = false;

    /** A hook wired before plugins load keeps its instance for the whole request (R-171). */
    #[Test]
    public function it_projects_the_plugin_card_once_the_plugin_has_loaded_after_the_wiring(): void
    {
        $projector = $this->deferred();
        $this->pluginIsActive = true;

        $this->assertSame(['from' => 'plugin'], $projector->project(new WP_Post((object) [])));
    }

    #[Test]
    public function it_projects_the_default_card_while_the_plugin_is_absent(): void
    {
        $this->assertSame(['from' => 'default'], $this->deferred()->project(new WP_Post((object) [])));
    }

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
