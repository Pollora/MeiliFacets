<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\ParentNode;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\PricePart;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The parts a project declared decide what is drawn. What always exists is the pair
 * of inputs: they carry the parameter names and the current bounds the client reads.
 */
final class PriceComponentTest extends TestCase
{
    /** @var array{concrete: \Closure, shared: bool} */
    private array $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = $this->app->getBindings()[SearchEngine::class];
        $this->app->forgetScopedInstances();
    }

    /** The suite shares one application: a fake engine left bound would answer a later class. */
    protected function tearDown(): void
    {
        $this->app->bind(SearchEngine::class, $this->engine['concrete'], $this->engine['shared']);
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function it_carries_both_bounds_as_inputs_whatever_it_shows(): void
    {
        foreach ([[], [PricePart::Fields], [PricePart::Slider], [PricePart::Slider, PricePart::Fields]] as $parts) {
            $document = $this->render($parts);

            foreach ([Hook::PriceMin, Hook::PriceMax] as $hook) {
                $this->assertNotNull($this->one($document, $hook), $hook->value.' is missing without '.count($parts).' parts.');
            }
        }
    }

    /** Declared with no part at all, it still reads the URL — so it keeps its inputs, hidden. */
    #[Test]
    public function it_hides_its_inputs_when_it_shows_nothing(): void
    {
        $this->assertSame('hidden', $this->one($this->render([]), Hook::PriceMin)->getAttribute('type'));
        $this->assertSame('number', $this->one($this->render([PricePart::Fields]), Hook::PriceMin)->getAttribute('type'));
    }

    /** Alone, an empty field is an open end; beside the control it mirrors the handle. */
    #[Test]
    public function it_leaves_a_field_empty_when_nothing_was_asked(): void
    {
        $this->assertSame('', $this->one($this->render([PricePart::Fields]), Hook::PriceMin)->getAttribute('value'));
        $this->assertNotSame('', $this->one($this->render([PricePart::Slider, PricePart::Fields]), Hook::PriceMin)->getAttribute('value'));
    }

    #[Test]
    public function it_shows_back_what_the_url_asked_for(): void
    {
        $document = $this->render([PricePart::Fields], ['min_price' => '40', 'max_price' => '70']);

        $this->assertSame('40', $this->one($document, Hook::PriceMin)->getAttribute('value'));
        $this->assertSame('70', $this->one($document, Hook::PriceMax)->getAttribute('value'));
    }

    /**
     * Without it the stylesheet falls back to `--from: 0` and `--to: 1`, and the
     * whole track reads as selected until a script runs.
     */
    #[Test]
    public function it_draws_the_filled_part_of_the_track_before_any_script_runs(): void
    {
        $document = $this->render([PricePart::Slider], ['min_price' => '55', 'max_price' => '120']);
        $style = $this->one($document, Hook::PriceRange)->getAttribute('style');

        $this->assertMatchesRegularExpression('/--from: 0\.\d+/', (string) $style);
        $this->assertMatchesRegularExpression('/--to: 0\.\d+/', (string) $style);
    }

    /**
     * A field carries a bare number, so a control without a range formats nothing:
     * `wc_price()` reads the currency, the decimals and both separators each time.
     */
    #[Test]
    public function it_formats_no_price_when_it_draws_no_range(): void
    {
        $this->assertStringNotContainsString('aria-valuetext', $this->render([PricePart::Fields])->body->innerHTML);
        $this->assertStringContainsString('aria-valuetext', $this->render([PricePart::Slider])->body->innerHTML);
    }

    #[Test]
    public function it_renders_hidden_and_empty_when_the_engine_reports_no_bounds(): void
    {
        $this->app->scoped(SearchEngine::class, fn (): FakeSearchEngine => new FakeSearchEngine);

        $document = $this->render([PricePart::Slider, PricePart::Fields]);

        $this->assertTrue($this->one($document, Hook::Facet)?->hasAttribute('hidden'));

        foreach ([Hook::PriceMin, Hook::PriceMax] as $hook) {
            $input = $this->one($document, $hook);

            $this->assertNotNull($input, $hook->value.' left the markup with no bounds.');
            $this->assertSame('', $input->getAttribute('value'), $hook->value.' invented a bound.');
        }
    }

    /** It is placed like a facet, but a fold button folds values, and a range has none. */
    #[Test]
    public function it_is_a_facet_host_with_nothing_to_fold(): void
    {
        $facet = $this->one($this->render([PricePart::Fields]), Hook::Facet);

        $this->assertNotNull($facet);
        $this->assertNull($this->one($facet, Hook::More));
        $this->assertNull($this->one($facet, Hook::FacetValue));
    }

    #[Test]
    public function it_refuses_to_place_a_term_facet(): void
    {
        $this->expectExceptionMessageMatches('/place it with the component of its own kind/');

        Blade::render('<x-meilifacets::price facet="'.$this->aFacetName().'" />');
    }

    /**
     * The filter is declared here, not read off the host project: adding one to the
     * shop must not decide what this suite asserts.
     *
     * @param  list<PricePart>  $parts
     * @param  array<string, string>  $query
     */
    private function render(array $parts, array $query = []): HTMLDocument
    {
        // Replace, never merge: merging leaves one test reading the parameters of the last.
        request()->replace($query);
        $this->app->forgetScopedInstances();

        $markup = Blade::render(
            '<x-meilifacets::price :facet="$filter" />',
            ['filter' => new PriceFilter('Price', $parts, 'price-under-test')]
        );

        return HTMLDocument::createFromString('<div>'.$markup.'</div>', LIBXML_NOERROR);
    }

    private function one(ParentNode $within, Hook $hook): ?Element
    {
        return $within->querySelector('['.Contract::Attribute->value.'="'.$hook->value.'"]');
    }

    private function aFacetName(): string
    {
        return $this->app->make(CurrentListing::class)->sole()->facets()[0]->name;
    }
}
