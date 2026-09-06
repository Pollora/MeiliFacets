<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StateReaderTest extends TestCase
{
    private StateReader $reader;

    private FakeListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new StateReader(new UrlParameters(['product_brand' => 'brand']));
        $this->listing = FakeListing::withBrandAndCategory();
    }

    #[Test]
    public function it_reads_a_mapped_parameter_under_its_configured_name(): void
    {
        $state = $this->reader->read($this->listing, ['brand' => 'acme']);

        $this->assertSame(['acme'], $state->selected('product_brand'));
    }

    #[Test]
    public function it_reads_an_unmapped_taxonomy_under_its_prefixed_name(): void
    {
        $state = $this->reader->read($this->listing, ['f_product_cat' => 'coats']);

        $this->assertSame(['coats'], $state->selected('product_cat'));
    }

    #[Test]
    public function it_ignores_a_bare_taxonomy_name(): void
    {
        $state = $this->reader->read($this->listing, ['product_cat' => 'coats']);

        $this->assertSame([], $state->selected('product_cat'));
    }

    // One state, one URL: an array form would give Varnish a second cache entry.
    #[Test]
    public function it_reads_the_comma_form_only(): void
    {
        $comma = $this->reader->read($this->listing, ['brand' => 'acme,globex']);
        $array = $this->reader->read($this->listing, ['brand' => ['acme', 'globex']]);

        $this->assertSame(['acme', 'globex'], $comma->selected('product_brand'));
        $this->assertSame([], $array->selected('product_brand'));
    }

    /** A sort the listing does not declare must not create a page of its own. */
    #[Test]
    public function it_drops_a_sort_the_listing_does_not_declare(): void
    {
        $this->assertSame('price_asc', $this->reader->read($this->listing, ['sort' => 'price_asc'])->sort);
        $this->assertNull($this->reader->read($this->listing, ['sort' => 'made_up'])->sort);
    }

    /** One state, one URL: values are sorted so Varnish caches it once. */
    #[Test]
    public function it_sorts_the_values_it_reads(): void
    {
        $this->assertSame(
            ['acme', 'globex'],
            $this->reader->read($this->listing, ['brand' => 'globex,acme'])->selected('product_brand')
        );
    }

    #[Test]
    public function it_drops_a_value_repeated_in_the_url(): void
    {
        $this->assertSame(
            ['acme'],
            $this->reader->read($this->listing, ['brand' => 'acme,acme'])->selected('product_brand')
        );
    }

    /** A crafted URL must not turn into thousands of filter clauses. */
    #[Test]
    public function it_caps_the_number_of_values_a_facet_can_hold(): void
    {
        $many = implode(',', array_map(static fn (int $i): string => 'v'.$i, range(1, 500)));

        $this->assertCount(30, $this->reader->read($this->listing, ['brand' => $many])->selected('product_brand'));
    }

    #[Test]
    public function it_caps_the_length_of_the_search_term(): void
    {
        $this->assertSame(200, mb_strlen($this->reader->read($this->listing, ['q' => str_repeat('a', 5000)])->query));
    }

    #[Test]
    public function it_keeps_a_single_value_for_a_single_selection_facet(): void
    {
        $state = $this->reader->read($this->listing, ['f_product_cat' => 'shirts,coats']);

        $this->assertSame(['coats'], $state->selected('product_cat'));
    }

    #[Test]
    public function it_drops_empty_and_blank_values(): void
    {
        $state = $this->reader->read($this->listing, ['brand' => 'acme,, ,globex']);

        $this->assertSame(['acme', 'globex'], $state->selected('product_brand'));
    }

    #[Test]
    public function it_survives_a_parameter_sent_as_a_nested_array(): void
    {
        $state = $this->reader->read($this->listing, ['brand' => [['acme']], 'sort' => ['x']]);

        $this->assertSame([], $state->selected('product_brand'));
        $this->assertNull($state->sort);
    }

    /** The path a visitor followed is merged in as `pg`, so an explicit one has to win. */
    #[Test]
    public function it_reads_the_page_from_the_parameter_it_was_given(): void
    {
        $state = $this->reader->read($this->listing, ['pg' => '3']);

        $this->assertSame(3, $state->page);
    }

    #[Test]
    public function it_falls_back_to_the_first_page(): void
    {
        foreach (['', 'abc', '0', '-3'] as $raw) {
            $this->assertSame(ListingState::FIRST_PAGE, $this->reader->read($this->listing, ['pg' => $raw])->page);
        }

        $this->assertSame(3, $this->reader->read($this->listing, ['pg' => '3'])->page);
    }

    #[Test]
    public function it_counts_the_filters_a_visitor_has_applied(): void
    {
        $state = $this->reader->read($this->listing, ['brand' => 'acme,globex', 'f_product_cat' => 'coats']);

        $this->assertSame(3, $state->activeFilterCount());
        $this->assertFalse($state->isPristine());
    }

    #[Test]
    public function it_reports_an_untouched_listing_as_pristine(): void
    {
        $this->assertTrue($this->reader->read($this->listing, [])->isPristine());
    }
}
