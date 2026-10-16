<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Modules\MeiliFacets\Enums\ActiveValueKind;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\View\ActiveValue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** One pill per filter the URL holds, drawn by the server so the page reads right before the client starts. */
final class ActiveValuesComponentTest extends TestCase
{
    use FindsHooks;
    use SwitchesTheSiteLocale;

    /** The suite shares one application: a listing resolved here would reach a later class. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetScopedInstances();
    }

    protected function tearDown(): void
    {
        request()->query->replace([]);
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function it_draws_a_pill_per_value_the_url_holds(): void
    {
        [$taxonomy, $value] = $this->aValue();
        $parameter = $this->parameters()->for($taxonomy);
        $this->ask([$parameter => $value->slug]);

        $html = $this->underLocales('en', 'en_US', $this->renderComponent(...));
        [$pill] = $this->pills($html);

        $this->assertSame($parameter, $pill->getAttribute('name'));
        $this->assertSame($value->slug, $pill->getAttribute('value'));
        $this->assertSame('term', $pill->getAttribute('data-kind'));
        $this->assertSame('Remove the '.$value->label.' filter', $pill->getAttribute('aria-label'));
        $this->assertSame($value->label, $this->labelOf($pill));
        $this->assertFalse($this->host($html)->hasAttribute('hidden'));
    }

    /** Named by a visually hidden text read before the pills, which hides with the list when nothing is held. */
    #[Test]
    public function it_names_the_list_by_a_text_read_before_its_pills(): void
    {
        $host = $this->host($this->underLocales('en', 'en_US', $this->renderComponent(...)));
        $name = $host->firstElementChild;

        $this->assertFalse($host->hasAttribute('aria-label'));
        $this->assertSame('SPAN', $name?->tagName);
        $this->assertSame('meilifacetsHidden', $name->getAttribute('class'));
        $this->assertSame('Active filters', $name->textContent);
        $this->assertSame('UL', $name->nextElementSibling?->tagName);
    }

    #[Test]
    public function it_draws_the_price_range_as_one_pill_in_the_language_of_the_site(): void
    {
        $this->ask([
            $this->parameters()->reserved(QueryParameter::MinPrice) => '10',
            $this->parameters()->reserved(QueryParameter::MaxPrice) => '50',
        ]);

        [$html, $expected] = $this->underLocales('fr', 'fr_FR', fn (): array => [$this->renderComponent(), $this->removedRange('10,00', '50,00')]);

        $this->assertMatchesRegularExpression($expected, $html);
        $this->assertStringContainsString('10,00', $html);
        $prices = array_filter($this->pills($html), fn (Element $pill): bool => $pill->getAttribute('data-kind') === 'price');
        $this->assertCount(1, $prices);
        $this->assertSame($this->parameters()->reserved(QueryParameter::MinPrice), array_values($prices)[0]->getAttribute('name'));
    }

    /** Read from the catalogue, so a translation reworded there does not break the test. */
    private function removedRange(string $min, string $max): string
    {
        $range = __(':min – :max', ['min' => 'MINIMUM', 'max' => 'MAXIMUM']);
        $label = __('Remove the :label filter', ['label' => $range]);
        $pattern = str_replace(['MINIMUM', 'MAXIMUM'], [preg_quote($min, '/').'.*', preg_quote($max, '/').'.*'], preg_quote(e($label), '/'));

        return '/aria-label="'.$pattern.'/u';
    }

    /**
     * The searched text is a filter like the others, named first. Placed against the
     * price, which needs no catalogue: `ActiveValueListTest` places it against a ticked value.
     */
    #[Test]
    public function it_draws_the_searched_text_first(): void
    {
        $query = $this->parameters()->reserved(QueryParameter::Query);
        $this->ask([$query => 'se"r', $this->parameters()->reserved(QueryParameter::MinPrice) => '10']);

        $pills = $this->pills($this->underLocales('en', 'en_US', $this->renderComponent(...)));

        $this->assertSame(['search', 'price'], array_map(fn (Element $pill): string => $pill->getAttribute('data-kind'), $pills));
        $this->assertSame($query, $pills[0]->getAttribute('name'));
        $this->assertSame('', $pills[0]->getAttribute('value'));
        $this->assertSame('Remove the “se"r” filter', $pills[0]->getAttribute('aria-label'));
        $this->assertSame('“se"r”', $this->labelOf($pills[0]));
    }

    #[Test]
    public function it_ignores_a_value_the_page_has_no_words_for(): void
    {
        [$taxonomy] = $this->aValue();
        $this->ask([$this->parameters()->for($taxonomy) => '<script>alert(1)</script>']);

        $html = $this->renderComponent();

        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertSame(1, substr_count($html, Hook::ActiveValue->attribute()->toHtml()), 'only the template holds a pill');
    }

    /** Rendered empty too: the client draws into the list, it never creates one. */
    #[Test]
    public function it_renders_a_hidden_list_and_its_template_when_nothing_is_held(): void
    {
        $html = $this->renderComponent();
        $opening = explode('>', $html)[0];

        $this->assertStringContainsString(' hidden', $opening);
        $this->assertStringContainsString(Hook::ActiveValues->attribute()->toHtml(), $opening);
        $this->assertMatchesRegularExpression('/<template '.preg_quote(Hook::ActiveValueTemplate->attribute()->toHtml(), '/').'>\s*<li[^>]*><button type="button" '.preg_quote(Hook::ActiveValue->attribute()->toHtml(), '/').'>/', $html);
    }

    #[Test]
    public function it_keeps_the_classes_a_theme_adds(): void
    {
        $this->assertStringContainsString(
            'class="meilifacetsActiveValues flex"',
            Blade::render('<x-meilifacets::listing.active-values name="products" class="flex" />'),
        );
    }

    /** A term name is typed by a shop manager, and a theme can override the catalogue. */
    #[Test]
    public function it_escapes_what_it_writes(): void
    {
        $hostile = '"><script>alert(1)</script>';

        $html = (string) view('meilifacets::components.listing.active-values', [
            'values' => [new ActiveValue($hostile, 'brand', $hostile, $hostile, ActiveValueKind::Term)],
            'attributes' => new ComponentAttributeBag,
            'hook' => fn (string $name): HtmlString => Hook::from($name)->attribute(),
        ])->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertSame(3, substr_count($html, '&quot;&gt;&lt;script&gt;'));
    }

    private function host(string $html): Element
    {
        $host = HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelector($this->hooked(Hook::ActiveValues));
        $this->assertNotNull($host, 'The component renders no list.');

        return $host;
    }

    /**
     * The pills the server drew, the template's left out.
     *
     * @return list<Element>
     */
    private function pills(string $html): array
    {
        return iterator_to_array($this->host($html)->querySelectorAll('ul > li > '.$this->hooked(Hook::ActiveValue)), false);
    }

    private function labelOf(Element $pill): string
    {
        return trim((string) $pill->firstChild?->textContent);
    }

    /**
     * @return array{string, FacetValue}
     */
    private function aValue(): array
    {
        $listing = $this->app->make(CurrentListing::class)->named(ProductListing::NAME);

        foreach ($listing->facets() as $facet) {
            $values = $listing->valuesOf($facet);

            if ($values !== []) {
                $this->app->forgetScopedInstances();

                return [$facet->taxonomy, $values[0]];
            }
        }

        $this->markTestSkipped('The catalogue offers no facet value.');
    }

    /**
     * @param  array<string, string>  $query
     */
    private function ask(array $query): void
    {
        request()->query->replace($query);
        $this->app->forgetScopedInstances();
    }

    private function parameters(): UrlParameters
    {
        return $this->app->make(UrlParameters::class);
    }

    private function renderComponent(): string
    {
        return Blade::render('<x-meilifacets::listing.active-values name="products" />');
    }
}
