<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\FilterExpression;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FilterExpressionTest extends TestCase
{
    private Facet $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = new Facet('product_brand', 'Brand');
    }

    #[Test]
    public function it_leaves_a_single_value_unwrapped(): void
    {
        $this->assertSame(
            'facets.product_brand = "acme"',
            FilterExpression::facet($this->brand, ['acme'])
        );
    }

    #[Test]
    public function it_joins_the_values_of_one_facet_with_or(): void
    {
        $this->assertSame(
            '(facets.product_brand = "acme" OR facets.product_brand = "globex")',
            FilterExpression::facet($this->brand, ['acme', 'globex'])
        );
    }

    #[Test]
    public function it_returns_nothing_for_a_facet_left_empty(): void
    {
        $this->assertSame('', FilterExpression::facet($this->brand, []));
    }

    #[Test]
    public function it_escapes_a_quote_so_it_cannot_close_the_value(): void
    {
        $this->assertSame(
            'facets.product_brand = "5\\" band"',
            FilterExpression::facet($this->brand, ['5" band'])
        );
    }

    // A value ending in a backslash would otherwise escape the closing quote and
    // let the rest of the URL through as filter syntax.
    #[Test]
    public function it_escapes_a_trailing_backslash(): void
    {
        $this->assertSame(
            'facets.product_brand = "back\\\\"',
            FilterExpression::facet($this->brand, ['back\\'])
        );
    }

    #[Test]
    public function it_keeps_an_injected_clause_inside_the_value(): void
    {
        $this->assertSame(
            'facets.product_brand = "x\\" OR post_status = \\"draft"',
            FilterExpression::facet($this->brand, ['x" OR post_status = "draft'])
        );
    }

    /**
     * `field != value` drops every document missing the field: measured on the
     * project index, it returned zero of twelve products.
     */
    #[Test]
    public function it_negates_an_equality_rather_than_using_a_difference(): void
    {
        $this->assertSame(
            'NOT facets.product_visibility = "exclude-from-catalog"',
            FilterExpression::without('facets.product_visibility', 'exclude-from-catalog')
        );
    }

    #[Test]
    public function it_drops_empty_clauses_when_joining(): void
    {
        $this->assertSame(
            'post_type = "product" AND x = "y"',
            FilterExpression::all(['post_type = "product"', '', 'x = "y"'])
        );
    }

    /**
     * Two intervals overlap unless one ends before the other starts. Writing it as
     * `min >= asked AND max <= asked` would keep only products that fit inside the
     * range, losing every one that merely reaches into it.
     */
    #[Test]
    public function it_asks_the_engine_for_an_overlap(): void
    {
        $this->assertSame(
            'price.min <= 70 AND price.max >= 40',
            FilterExpression::overlapping(new Range(40.0, 70.0))
        );
    }

    #[Test]
    public function it_leaves_an_open_end_unconstrained(): void
    {
        $this->assertSame('price.max >= 40', FilterExpression::overlapping(new Range(min: 40.0)));
        $this->assertSame('price.min <= 70', FilterExpression::overlapping(new Range(max: 70.0)));
        $this->assertSame('', FilterExpression::overlapping(new Range));
    }

    /**
     * Fixed notation to four decimals: `(string) 1.0E-9` is not a filter, and no
     * currency carries more than three. A bound is written as asked, not rounded
     * to the money format, so a hand-typed `99.999` still means what it says.
     */
    #[Test]
    public function it_writes_a_bound_the_engine_can_read(): void
    {
        $this->assertSame('price.min <= 99.999', FilterExpression::overlapping(new Range(max: 99.999)));
        $this->assertSame('price.min <= 0', FilterExpression::overlapping(new Range(max: 0.0)));
        $this->assertSame('price.min <= 12.5', FilterExpression::overlapping(new Range(max: 12.50)));
        $this->assertSame('price.min <= 1000000', FilterExpression::overlapping(new Range(max: 1e6)));
    }
}
