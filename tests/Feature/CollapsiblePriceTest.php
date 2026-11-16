<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\ParentNode;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\PricePart;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Tests\Feature\Concerns\FindsHooks;
use Tests\TestCase;

trait CollapsiblePriceSteps
{
    private const string LABEL = 'Price';

    private function withoutDisclosure(HTMLDocument $document): string
    {
        foreach ($document->querySelectorAll($this->hooked(Hook::Panel)) as $panel) {
            $panel->removeAttribute('hidden');
            $panel->removeAttribute(Contract::Attribute->value);
        }

        return $this->withoutLegend($document);
    }

    private function withoutLegend(HTMLDocument $document): string
    {
        $document->querySelector('legend')->remove();

        return $document->saveHtml();
    }

    /**
     * Read off the catalogue, which changes with every import: fixed amounts would fall outside it.
     *
     * @return array{float, float}
     */
    private function catalogueBounds(): array
    {
        $handle = $this->one($this->render(), Hook::PriceHandle);

        $this->assertNotNull($handle, 'The catalogue has no price to draw a track from.');

        return [(float) $handle->getAttribute('aria-valuemin'), (float) $handle->getAttribute('aria-valuemax')];
    }

    /**
     * @param  array<string, string>  $query
     */
    private function render(string $attributes = '', array $query = []): HTMLDocument
    {
        request()->replace($query);
        $this->app->forgetScopedInstances();

        $markup = Blade::render(
            '<x-meilifacets::listing.price name="products" :facet="$filter" '.$attributes.' />',
            ['filter' => new PriceFilter(self::LABEL, [PricePart::Slider, PricePart::Fields], 'price-under-test')]
        );

        return HTMLDocument::createFromString('<div>'.$markup.'</div>', LIBXML_NOERROR);
    }

    private function one(ParentNode $within, Hook $hook): ?Element
    {
        return $within->querySelector($this->hooked($hook));
    }
}

// Step 4c of the filter bar: the price behind the same trigger as a facet, closed until the client opens it.
uses(TestCase::class, FindsHooks::class, CollapsiblePriceSteps::class);

beforeEach(function (): void {
    $this->app->forgetScopedInstances();
});

afterEach(function (): void {
    request()->replace([]);
    $this->app->forgetScopedInstances();
});

// With a slider the plain legend is visually hidden (`namesItself()`); a trigger has to be seen.
it('names the price with a visible trigger inside its legend', function (): void {
    $legend = $this->render('collapsible')->querySelector('legend');
    $toggle = $legend->querySelector($this->hooked(Hook::Toggle));

    $this->assertNotNull($toggle, 'The legend holds no trigger.');
    expect($legend->getAttribute('class'))->toBe('meilifacetsFacetLabel');
    expect($toggle->querySelector('.meilifacetsFacetToggleLabel')->textContent)->toBe(self::LABEL);
});

// Q-4: closed on the server, or the panel flashes open until the client runs.
it('renders the panel closed and points the trigger at it', function (): void {
    $document = $this->render('collapsible');
    $toggle = $this->one($document, Hook::Toggle);
    $panel = $document->getElementById($toggle->getAttribute('aria-controls'));

    expect($toggle->getAttribute('aria-expanded'))->toBe('false');
    $this->assertNotNull($panel, 'aria-controls names no element.');
    expect($panel->matches($this->hooked(Hook::Panel)))->toBeTrue();
    expect($panel->hasAttribute('hidden'))->toBeTrue();
    $this->assertNotNull($panel->querySelector($this->hooked(Hook::PriceTrack)), 'The track is not in the panel.');
});

it('hides an empty badge while no range is held', function (): void {
    $badge = $this->one($this->render('collapsible'), Hook::SelectedCount);

    expect($badge->hasAttribute('hidden'))->toBeTrue();
    expect($badge->textContent)->toBe('');
});

// `R-123`: a range is one filter, whether it holds one end or two.
it('counts a held range once on the badge', function (): void {
    [$low, $high] = $this->catalogueBounds();
    $inside = ['min_price' => (string) ($low + 1), 'max_price' => (string) ($high - 1)];

    foreach ([$inside, ['max_price' => $inside['max_price']]] as $query) {
        $badge = $this->one($this->render('collapsible', $query), Hook::SelectedCount);

        expect($badge->hasAttribute('hidden'))->toBeFalse('No badge for '.http_build_query($query).'.');
        expect($badge->textContent)->toBe('1');
    }
});

// Measured once against the view before step 4c, byte for byte; what can move is held here.
it('differs from the plain price by the legend and the closed panel only', function (): void {
    $plain = $this->render();
    $collapsible = $this->render('collapsible');

    expect(trim($plain->querySelector('legend')->textContent))->toBe(self::LABEL);
    expect($this->one($plain, Hook::Toggle))->toBeNull();
    expect($this->one($plain, Hook::Panel))->toBeNull();
    expect($this->one($plain, Hook::SelectedCount))->toBeNull();
    expect($this->withoutDisclosure($collapsible))->toBe($this->withoutLegend($plain));
});

// A view a theme copies must hold markup only (`R-151`).
it('leaves every computation of the trigger to the component', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/listing/price.blade.php');

    expect($view)->not->toContain('@php');
    expect($view)->not->toContain('$ids->');
    expect($view)->not->toContain('$listing->');
});
