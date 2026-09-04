<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
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
        $this->assertSame(10, count(array_filter($values, static fn ($v): bool => ! $v->folded)));
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
        $this->assertSame(2, count(array_filter($values, static fn ($v): bool => ! $v->folded)));
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

    private function values(): FacetValues
    {
        return new FacetValues(new FakeTermLabels(['a' => 'Acme']));
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

    /**
     * @param  array<string, int>  $distribution
     * @return list<FacetValue>
     */
    private function build(array $distribution, Facet $facet): array
    {
        return $this->values()->of($facet, $distribution, new ListingState);
    }
}
