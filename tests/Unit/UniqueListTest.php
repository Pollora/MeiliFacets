<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Support\UniqueList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UniqueListTest extends TestCase
{
    #[Test]
    public function it_keeps_each_value_where_it_first_appears(): void
    {
        $this->assertSame(['title', 'excerpt', 'content'], UniqueList::merge(['title', 'excerpt'], ['content', 'title']));
    }

    #[Test]
    public function it_drops_a_repeat_inside_a_single_list(): void
    {
        $this->assertSame(['red', 'blue'], UniqueList::merge(['red', 'blue', 'red']));
    }

    #[Test]
    public function it_hands_back_a_list_with_no_gap_in_its_keys(): void
    {
        $merged = UniqueList::merge(['a', 'a'], ['b']);

        $this->assertTrue(array_is_list($merged));
    }

    #[Test]
    public function it_keeps_values_that_differ_only_by_case(): void
    {
        $this->assertSame(['Red', 'red'], UniqueList::merge(['Red'], ['red']));
    }

    #[Test]
    public function it_merges_nothing_into_an_empty_list(): void
    {
        $this->assertSame([], UniqueList::merge());
        $this->assertSame([], UniqueList::merge([], []));
    }
}
