<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\DefaultTerm;
use Modules\MeiliFacets\Enums\DisplayOrder;
use Modules\MeiliFacets\Listing\ChildTermsFacet;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Labels come from WordPress, so these cases exercise the folding and the cap,
 * which are the parts that hold without it.
 */
final class FacetValuesTest extends TestCase
{
    #[Test]
    public function it_folds_everything_past_the_visible_limit(): void
    {
        $values = $this->build($this->distribution(14), new Facet('brand', 'Brand'));

        $this->assertCount(14, $values);
        $this->assertCount(10, array_filter($values, static fn ($v): bool => ! $v->folded));
        $this->assertTrue($values[10]->folded);
        $this->assertFalse($values[9]->folded);
    }

    #[Test]
    public function it_drops_everything_past_the_cap(): void
    {
        $values = $this->build($this->distribution(40), new Facet('brand', 'Brand'));

        $this->assertCount(Facet::DEFAULT_CAP, $values);
    }

    #[Test]
    public function it_honours_a_facet_that_sets_its_own_limits(): void
    {
        $values = $this->build($this->distribution(10), new Facet('brand', 'Brand', visible: 2, cap: 4));

        $this->assertCount(4, $values);
        $this->assertCount(2, array_filter($values, static fn ($v): bool => ! $v->folded));
    }

    #[Test]
    public function it_keeps_the_order_the_engine_returned(): void
    {
        $values = $this->build(['b' => 9, 'a' => 5, 'c' => 1], new Facet('brand', 'Brand'));

        $this->assertSame(['b', 'a', 'c'], array_map(static fn ($v): string => $v->slug, $values));
    }

    #[Test]
    public function it_marks_the_values_the_url_holds(): void
    {
        $state = new ListingState(['brand' => ['a']]);
        $values = $this->values()->of(new Facet('brand', 'Brand'), ['a' => 2, 'b' => 3], $state);

        $this->assertTrue($values[0]->selected);
        $this->assertFalse($values[1]->selected);
    }

    #[Test]
    public function it_shows_the_values_in_the_order_the_facet_asked_for(): void
    {
        $facet = new Facet('size', 'Volume', order: new NameOrder);
        $values = $this->build(['500ml' => 9, '10ml' => 5, '50ml' => 1], $facet);

        $this->assertSame(['10ml', '50ml', '500ml'], array_map(static fn ($v): string => $v->slug, $values));
    }

    /** What is read is the head of the declared order, not the best counted. */
    #[Test]
    public function it_folds_what_the_display_order_puts_last(): void
    {
        $facet = new Facet('size', 'Volume', visible: 2, order: new NameOrder);
        $values = $this->build(['b' => 9, 'z' => 8, 'a' => 1], $facet);

        $folded = array_column(array_filter($values, static fn ($v): bool => $v->folded), 'slug');

        $this->assertSame(['a', 'b', 'z'], array_map(static fn ($v): string => $v->slug, $values));
        $this->assertSame(['z'], array_values($folded));
    }

    /** The two limits answer to two different masters: the engine spends the cap, the facet the fold. */
    #[Test]
    public function it_spends_the_cap_on_the_count_and_the_fold_on_the_order(): void
    {
        $facet = new Facet('size', 'Volume', visible: 1, cap: 2, order: new NameOrder);
        $values = $this->build(['b' => 9, 'z' => 8, 'a' => 1], $facet);

        // "a" sorts first but counts last: the cap drops it before the order is applied.
        $this->assertSame(['b', 'z'], array_map(static fn ($v): string => $v->slug, $values));
        $this->assertSame([false, true], array_map(static fn ($v): bool => $v->folded, $values));
    }

    /** A held value the visitor cannot see is a filter they cannot lift. */
    #[Test]
    public function it_never_folds_away_a_value_the_url_holds(): void
    {
        $facet = new Facet('brand', 'Brand', visible: 1);
        $values = $this->values()->of($facet, ['a' => 9, 'b' => 8, 'c' => 7], new ListingState(['brand' => ['c']]));

        $this->assertSame(['a', 'b', 'c'], array_map(static fn (FacetValue $v): string => $v->slug, $values));
        $this->assertSame([false, true, false], array_map(static fn (FacetValue $v): bool => $v->folded, $values));
    }

    /** The taxonomy already carries an order a shop set; the module reads it rather than inventing one. */
    #[Test]
    public function it_shows_the_values_in_the_order_the_taxonomy_declares(): void
    {
        $labels = new FakeTermLabels(['50ml' => '50ml', '5ml' => '5ml', '4g' => '4g']);
        $facet = new Facet('pa_contenance', 'Volume', order: DisplayOrder::Declared);

        $values = new FacetValues($labels, new FakeTermScope, new FakeDefaultTerms)
            ->of($facet, ['4g' => 9, '50ml' => 5, '5ml' => 1], new ListingState);

        $this->assertSame(['50ml', '5ml', '4g'], array_map(static fn (FacetValue $v): string => $v->slug, $values));
    }

    /** A slug the taxonomy no longer lists still has a count: it waits at the end. */
    #[Test]
    public function it_sends_a_value_the_taxonomy_does_not_list_to_the_end(): void
    {
        $labels = new FakeTermLabels(['b' => 'B', 'a' => 'A']);
        $facet = new Facet('pa_contenance', 'Volume', order: DisplayOrder::Declared);

        $values = new FacetValues($labels, new FakeTermScope, new FakeDefaultTerms)
            ->of($facet, ['orphan' => 9, 'a' => 5, 'b' => 1], new ListingState);

        $this->assertSame(['b', 'a', 'orphan'], array_map(static fn (FacetValue $v): string => $v->slug, $values));
    }

    private function values(): FacetValues
    {
        return new FacetValues(new FakeTermLabels(['a' => 'Acme']), new FakeTermScope, new FakeDefaultTerms);
    }

    /**
     * @return array<string, int>
     */
    private function distribution(int $count): array
    {
        return array_combine(
            array_map(static fn (int $i): string => 'value-'.$i, range(1, $count)),
            array_fill(0, $count, 1)
        );
    }

    /** The cap is spent on what the facet may show, not on what the engine returned. */
    #[Test]
    public function it_caps_a_scoped_facet_after_scoping_it(): void
    {
        $values = new FacetValues(
            new FakeTermLabels([]),
            new FakeTermScope(['product_cat' => ['b', 'c']]),
            new FakeDefaultTerms
        )->of(
            new ChildTermsFacet('product_cat', 'Category', cap: 2),
            ['a' => 9, 'b' => 8, 'c' => 7, 'd' => 6],
            new ListingState
        );

        $this->assertSame(['b', 'c'], array_map(static fn (FacetValue $v): string => $v->slug, $values));
    }

    /** « Non classé » says a content was filed nowhere: that is not a way to browse. */
    #[Test]
    public function it_drops_the_term_a_taxonomy_falls_back_to(): void
    {
        $values = $this->withFallback()->of(
            new Facet('product_cat', 'Category'),
            ['a' => 4, 'non-classe' => 1],
            new ListingState
        );

        $this->assertSame(['a'], array_map(static fn (FacetValue $v): string => $v->slug, $values));
    }

    /** A taxonomy whose fallback is a term an editor chose keeps it. */
    #[Test]
    public function it_keeps_the_fallback_a_facet_asks_to_show(): void
    {
        $values = $this->withFallback()->of(
            new Facet('product_cat', 'Category', defaultTerm: DefaultTerm::Shown),
            ['a' => 4, 'non-classe' => 1],
            new ListingState
        );

        $this->assertCount(2, $values);
    }

    #[Test]
    public function it_leaves_a_taxonomy_without_a_fallback_alone(): void
    {
        $values = $this->withFallback()->of(
            new Facet('product_brand', 'Brand'),
            ['a' => 4, 'b' => 1],
            new ListingState
        );

        $this->assertCount(2, $values);
    }

    /** A taxonomy that has a fallback term, and one that has none. */
    private function withFallback(): FacetValues
    {
        return new FacetValues(
            new FakeTermLabels([]),
            new FakeTermScope,
            new FakeDefaultTerms(['product_cat' => 'non-classe'])
        );
    }

    /**
     * @param  array<string, int>  $distribution
     * @return list<FacetValue>
     */
    private function build(array $distribution, Facet $facet): array
    {
        return $this->values()->of($facet, $distribution, new ListingState);
    }
}
