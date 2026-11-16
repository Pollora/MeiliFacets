<?php

declare(strict_types=1);

// Defining `YoastSEO()` is what tells the module Yoast SEO is active: loaded by `WordPress::bootYoast()`, from a
// test that runs in its own process only.

namespace Yoast\WP\SEO\Models {
    class Indexable
    {
        public string $canonical = '';
    }
}

namespace {
    use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;

    if (! function_exists('YoastSEO')) {
        /** The service graph a test arranges with `WordPress::fake('YoastSEO', ...)`. */
        function YoastSEO(): object
        {
            return WordPress::call('YoastSEO', []);
        }
    }
}
