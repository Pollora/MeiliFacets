<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\TermField;
use Modules\MeiliFacets\Indexing\TermGrouping;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TermGroupingTest extends TestCase
{
    #[Test]
    public function it_lists_the_kept_field_under_each_taxonomy_in_the_order_met(): void
    {
        $grouped = new TermGrouping(TermField::Slug)->group([
            $this->term('pa_colour', 'red', 'Red'),
            $this->term('product_cat', 'soaps', 'Soaps'),
            $this->term('pa_colour', 'blue', 'Blue'),
        ]);

        $this->assertSame(['pa_colour' => ['red', 'blue'], 'product_cat' => ['soaps']], $grouped);
    }

    #[Test]
    public function it_reads_the_field_it_was_built_to_keep(): void
    {
        $grouped = new TermGrouping(TermField::Name)->group([$this->term('pa_colour', 'red', 'Red')]);

        $this->assertSame(['pa_colour' => ['Red']], $grouped);
    }

    #[Test]
    public function it_keeps_a_repeated_value_once_as_a_list(): void
    {
        $grouped = new TermGrouping(TermField::Slug)->group([
            $this->term('pa_colour', 'red', 'Red'),
            $this->term('pa_colour', 'blue', 'Blue'),
            $this->term('pa_colour', 'red', 'Red'),
            $this->term('pa_colour', 'green', 'Green'),
        ]);

        $this->assertSame(['pa_colour' => ['red', 'blue', 'green']], $grouped);
    }

    #[Test]
    public function it_keeps_a_value_shared_by_two_taxonomies_in_both(): void
    {
        $grouped = new TermGrouping(TermField::Slug)->group([
            $this->term('pa_colour', 'red', 'Red'),
            $this->term('pa_finish', 'red', 'Red'),
        ]);

        $this->assertSame(['pa_colour' => ['red'], 'pa_finish' => ['red']], $grouped);
    }

    #[Test]
    public function it_leaves_out_a_term_with_no_taxonomy_or_no_kept_value(): void
    {
        $grouped = new TermGrouping(TermField::Slug)->group([
            [TermField::Slug->value => 'orphan'],
            $this->term('', 'blank-taxonomy', 'Blank'),
            [TermField::Taxonomy->value => 'pa_colour', TermField::Name->value => 'Slugless'],
            $this->term('pa_colour', '', 'Empty slug'),
            [TermField::Taxonomy->value => 'pa_colour', TermField::Slug->value => 42],
        ]);

        $this->assertSame([], $grouped);
    }

    #[Test]
    public function it_groups_nothing_out_of_no_term(): void
    {
        $this->assertSame([], new TermGrouping(TermField::Slug)->group([]));
    }

    /**
     * @return array<string, string>
     */
    private function term(string $taxonomy, string $slug, string $name): array
    {
        return [
            TermField::Taxonomy->value => $taxonomy,
            TermField::Slug->value => $slug,
            TermField::Name->value => $name,
        ];
    }
}
