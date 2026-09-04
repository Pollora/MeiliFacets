<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\Facet;
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
}
