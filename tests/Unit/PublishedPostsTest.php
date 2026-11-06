<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\PublishedPosts;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PublishedPostsTest extends TestCase
{
    #[Test]
    public function it_keeps_only_the_published_posts_of_one_type(): void
    {
        $this->assertSame(['post_type = "product"', 'post_status = "publish"'], PublishedPosts::of('product'));
    }

    #[Test]
    public function it_quotes_a_post_type_that_would_break_out_of_the_filter(): void
    {
        $this->assertSame('post_type = "x\" OR post_type = \"page"', PublishedPosts::of('x" OR post_type = "page')[0]);
    }
}
