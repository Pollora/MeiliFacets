<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\PublishedPosts;

use Modules\MeiliFacets\Listing\PublishedPosts;

it('keeps only the published posts of one type', function (): void {
    expect(PublishedPosts::of('product'))->toBe(['post_type = "product"', 'post_status = "publish"']);
});

it('quotes a post type that would break out of the filter', function (): void {
    expect(PublishedPosts::of('x" OR post_type = "page')[0])->toBe('post_type = "x\" OR post_type = \"page"');
});
