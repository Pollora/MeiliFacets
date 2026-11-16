<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\WooCommerce\AnonymousVisitor;

it('only reads on a site without wordpress', function (): void {
    if (function_exists('wp_set_current_user')) {
        $this->markTestSkipped('WordPress is loaded: another suite ran first.');
    }

    expect(new AnonymousVisitor()->during(static fn (): string => 'card'))->toBe('card');
});
