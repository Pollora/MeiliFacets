<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Enums\Presentation;
use Modules\MeiliFacets\Enums\PricePart;
use Modules\MeiliFacets\Enums\SortWidget;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Support\Money;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use Modules\MeiliFacets\View\Components\Listing\Facet as FacetComponent;
use Modules\MeiliFacets\View\Components\Listing\Price as PriceComponent;
use Modules\MeiliFacets\View\Components\Listing\Sort as SortComponent;
use Modules\MeiliFacets\View\CountLabel;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** The values a Blade view reads off its component, without rendering the view: see `Doubles\WordPress`. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ComponentsTest extends TestCase
{
    protected function setUp(): void
    {
        WordPress::boot();
        WordPress::fake('get_query_var', '');
        $container = new Container;
        $container->instance('view', new class
        {
            public function getConsumableComponentData(string $key): mixed
            {
                return null;
            }
        });
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }

    #[Test]
    public function a_facet_component_reads_its_values_and_names_its_ids_after_the_listing(): void
    {
        $facet = new Facet('product_brand', 'Brand', name: 'brand');
        $component = $this->facet($facet, ['acme' => 3, 'zest' => 0], ['f_product_brand' => 'acme']);

        $this->assertSame(['acme', 'zest'], array_map(static fn ($value): string => $value->slug, $component->values));
        $this->assertSame('checkbox', $component->inputType());
        $this->assertSame('f_product_brand', $component->inputName());
        $this->assertSame($component->ids->facetPanel('brand'), $component->panelId());
        $this->assertSame($component->ids->facetValueLabel('brand', 'acme'), $component->labelId($component->values[0]));
        $this->assertSame($component->ids->facetCount('brand', 'acme'), $component->countId($component->values[0]));
        $this->assertSame('3 results', $component->countLabel($component->values[0]));
        $this->assertSame('0 results', $component->countLabel($component->values[1]));
        $this->assertSame('Brand', $component->disclosure()->label);
        $this->assertFalse($component->marksPresentation());
        $this->assertTrue($component->isShown());
        $this->assertFalse($component->isBeyondRow());
        $this->assertFalse($component->hasFoldedValues());
        $this->assertSame('meilifacets::components.listing.facet', $component->render()->name());
    }

    #[Test]
    public function a_facet_component_takes_a_presentation_from_its_attribute_or_its_declaration(): void
    {
        $facet = new Facet('product_brand', 'Brand', name: 'brand');

        $this->assertTrue($this->facet($facet, ['acme' => 1], presentation: 'pill')->marksPresentation());
        $this->assertTrue($this->facet($facet, ['acme' => 1], presentation: Presentation::Pill)->marksPresentation());
        $this->assertFalse($this->facet($facet, ['acme' => 1], presentation: null)->marksPresentation());
    }

    #[Test]
    public function a_facet_component_is_hidden_when_the_engine_returned_no_value(): void
    {
        $component = $this->facet(new Facet('product_brand', 'Brand', name: 'brand'), []);

        $this->assertFalse($component->isShown());
    }

    #[Test]
    public function a_price_component_draws_one_handle_per_bound_inside_what_the_catalogue_offers(): void
    {
        $component = $this->price(['price.min' => ['min' => 10.4, 'max' => 10.4], 'price.max' => ['min' => 80.0, 'max' => 99.2]]);

        $this->assertTrue($component->isShown());
        $this->assertSame(10.0, $component->bounds()->min);
        $this->assertSame(100.0, $component->bounds()->max);
        $this->assertSame(['min', 'max'], array_map(static fn ($handle): string => $handle->bound->value, $component->handles()));
        $this->assertSame($component->handles(), $component->handles());
        $this->assertSame('min_price', $component->handles()[0]->parameter);
        $this->assertSame(0.0, $component->fill()->from);
        $this->assertSame(1.0, $component->fill()->to);
        $this->assertSame('10.00 – 100.00', $component->readout());
        $this->assertFalse($component->showsSlider());
        $this->assertTrue($component->showsFields());
        $this->assertFalse($component->isBeyondRow());
        $this->assertSame('Price', $component->disclosure()->label);
        $this->assertSame($component->ids->facetPanel('price'), $component->panelId());
        $this->assertSame('meilifacets::components.listing.price', $component->render()->name());
    }

    #[Test]
    public function a_price_component_with_a_slider_shows_the_effective_bounds_in_its_handles(): void
    {
        $component = $this->price(
            ['price.min' => ['min' => 10.0, 'max' => 10.0], 'price.max' => ['min' => 80.0, 'max' => 100.0]],
            parts: [PricePart::Slider],
        );

        $this->assertTrue($component->showsSlider());
        $this->assertFalse($component->showsFields());
        $this->assertSame([10.0, 100.0], array_map(static fn ($handle): ?float => $handle->shown, $component->handles()));
    }

    #[Test]
    public function a_price_component_clamps_the_bounds_the_visitor_asked_for_to_the_reachable_prices(): void
    {
        $component = $this->price(
            ['price.min' => ['min' => 10.0, 'max' => 10.0], 'price.max' => ['min' => 80.0, 'max' => 100.0]],
            ['min_price' => '5', 'max_price' => '60'],
        );

        [$low, $high] = $component->handles();

        $this->assertSame(10.0, $low->value);
        $this->assertSame(60.0, $high->value);
    }

    #[Test]
    public function a_price_component_is_hidden_without_a_price_range_to_offer(): void
    {
        $component = $this->price([]);

        $this->assertFalse($component->isShown());
        $this->assertTrue($component->bounds()->isEmpty());
    }

    #[Test]
    public function a_sort_component_offers_its_choices_and_a_caption_naming_the_current_one(): void
    {
        $component = $this->sort(['sort' => 'newest']);

        $this->assertTrue($component->shouldRender());
        $this->assertSame('Newest', $component->selected->label);
        $this->assertSame('Sort by', $component->caption->label);
        $this->assertSame($component->ids->sortPanel(), $component->panelId());
        $this->assertSame($component->ids->sortChoiceName(), $component->choiceName());
        $this->assertSame('meilifacets::components.listing.sort', $component->render()->name());
        $this->assertSame('Sort by', $component->disclosure()->label);
    }

    #[Test]
    public function a_sort_component_renders_radios_and_takes_a_caption_of_its_own(): void
    {
        $component = $this->sort([], widget: SortWidget::Radios, label: 'Order');

        $this->assertSame('meilifacets::components.listing.sort-radios', $component->render()->name());
        $this->assertSame('Order', $component->caption->label);
        $this->assertSame('Relevance', $component->selected->label);
    }

    #[Test]
    public function a_sort_component_with_only_relevance_to_offer_renders_nothing(): void
    {
        $listing = new FakeListing(sorts: []);

        $this->assertFalse($this->sortOf($listing)->shouldRender());
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, string>  $query
     */
    private function facet(Facet $facet, array $counts, array $query = [], Presentation|string|null $presentation = null): FacetComponent
    {
        $current = $this->current(new FakeListing([$facet]), ['facetDistribution' => ['facets.product_brand' => $counts]], $query);

        return new FacetComponent($current, new CountLabel(static fn (): string => 'en_US'), new UrlParameters, $facet, presentation: $presentation);
    }

    /**
     * @param  array<string, array<string, float>>  $stats
     * @param  array<string, string>  $query
     * @param  list<PricePart>  $parts
     */
    private function price(array $stats, array $query = [], array $parts = [PricePart::Fields]): PriceComponent
    {
        $current = $this->current(new FakeListing([new PriceFilter('Price', $parts)]), ['facetStats' => $stats], $query);

        return new PriceComponent($current, new Money, new UrlParameters, 'price');
    }

    /**
     * @param  array<string, string>  $query
     */
    private function sort(array $query, SortWidget $widget = SortWidget::Listbox, ?string $label = null): SortComponent
    {
        $listing = new FakeListing(sorts: ['price_asc' => new Sort('Price', ['price.min:asc']), 'newest' => new Sort('Newest', ['post_date:desc'])]);

        return $this->sortOf($listing, $query, $widget, $label);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function sortOf(FakeListing $listing, array $query = [], SortWidget $widget = SortWidget::Listbox, ?string $label = null): SortComponent
    {
        return new SortComponent($this->current($listing, [], $query), widget: $widget, label: $label);
    }

    /**
     * @param  array<string, mixed>  $results
     * @param  array<string, string>  $query
     */
    private function current(FakeListing $listing, array $results, array $query): CurrentListing
    {
        WordPress::$query = $query;
        $registry = new ListingRegistry(new Container);
        $registry->add($listing);
        $parameters = new UrlParameters;

        return new CurrentListing(
            $registry,
            new ListingSearch(new FakeSearchEngine(['results' => ['totalHits' => 1, 'hits' => [], ...$results]]), new DisjunctiveFacetCounter),
            new StateReader($parameters),
            new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
            $parameters,
            new EngineLimits(1000),
        );
    }
}
