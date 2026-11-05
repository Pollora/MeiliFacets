<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\SortDirection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SortDirectionTest extends TestCase
{
    #[Test]
    public function it_writes_a_meilisearch_sort_expression_for_a_field(): void
    {
        $this->assertSame('post_date:asc', SortDirection::Ascending->of('post_date'));
        $this->assertSame('price.max:desc', SortDirection::Descending->of('price.max'));
    }

    #[Test]
    public function it_reads_the_field_back_out_of_an_expression(): void
    {
        $this->assertSame('price.min', SortDirection::fieldOf('price.min:asc'));
    }

    #[Test]
    public function it_reads_an_expression_with_no_direction_as_the_field_itself(): void
    {
        $this->assertSame('post_title', SortDirection::fieldOf('post_title'));
    }
}
