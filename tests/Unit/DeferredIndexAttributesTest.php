<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\DeferredIndexAttributes;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\WooCommerceIndexAttributes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeferredIndexAttributesTest extends TestCase
{
    private bool $pluginIsActive = false;

    /** A hook wired before plugins load keeps its instance for the whole request (R-171). */
    #[Test]
    public function it_declares_the_plugin_attributes_once_the_plugin_has_loaded_after_the_wiring(): void
    {
        $attributes = $this->deferred();
        $this->pluginIsActive = true;

        $this->assertSame((new WooCommerceIndexAttributes)->filterable(), $attributes->filterable());
        $this->assertSame((new WooCommerceIndexAttributes)->sortable(), $attributes->sortable());
    }

    #[Test]
    public function it_declares_nothing_while_the_plugin_is_absent(): void
    {
        $attributes = $this->deferred();

        $this->assertSame([], $attributes->filterable());
        $this->assertSame([], $attributes->sortable());
        $this->assertSame([], $attributes->displayed());
    }

    private function deferred(): DeferredIndexAttributes
    {
        return new DeferredIndexAttributes(
            fn (): bool => $this->pluginIsActive,
            new WooCommerceIndexAttributes,
            new EmptyIndexAttributes
        );
    }
}
