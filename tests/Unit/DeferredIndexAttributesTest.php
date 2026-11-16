<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\DeferredIndexAttributes;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\WooCommerce\WooCommerceIndexAttributes;

beforeEach(function (): void {
    $this->pluginIsActive = false;
    $this->deferred = fn (): DeferredIndexAttributes => new DeferredIndexAttributes(
        fn (): bool => $this->pluginIsActive,
        new WooCommerceIndexAttributes,
        new EmptyIndexAttributes
    );
});

// A hook wired before plugins load keeps its instance for the whole request (R-171).
it('declares the plugin attributes once the plugin has loaded after the wiring', function (): void {
    $attributes = ($this->deferred)();
    $this->pluginIsActive = true;

    expect($attributes->filterable())->toBe((new WooCommerceIndexAttributes)->filterable());
    expect($attributes->sortable())->toBe((new WooCommerceIndexAttributes)->sortable());
    expect($attributes->exactlyMatched())->toBe((new WooCommerceIndexAttributes)->exactlyMatched());
});

it('declares nothing while the plugin is absent', function (): void {
    $attributes = ($this->deferred)();

    expect($attributes->filterable())->toBe([]);
    expect($attributes->sortable())->toBe([]);
    expect($attributes->displayed())->toBe([]);
    expect($attributes->exactlyMatched())->toBe([]);
});
