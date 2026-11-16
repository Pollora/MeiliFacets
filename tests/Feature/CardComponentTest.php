<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\Tests\Feature\Concerns\RendersTheModuleViews;
use Modules\MeiliFacets\View\CardBinding;
use Modules\MeiliFacets\View\Components\Listing\Card;
use Tests\TestCase;

trait CardComponentSupport
{
    private const string SRCSET = 'https://example.test/photo-300x300.jpg 300w, https://example.test/photo.jpg 600w';

    /**
     * @param  array<string, mixed>  $card
     */
    private function image(array $card): Element
    {
        $image = $this->document($this->render($card))->querySelector('img');

        $this->assertInstanceOf(Element::class, $image);

        return $image;
    }

    private function document(string $html): Element
    {
        $body = HTMLDocument::createFromString('<!doctype html><body>'.$html, LIBXML_NOERROR)->body;

        $this->assertInstanceOf(Element::class, $body);

        return $body;
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  array<string, mixed>  $props
     */
    private function render(array $card, array $props = []): string
    {
        return Blade::render(
            '<x-meilifacets::listing.card :binding="$binding" :heading="$heading" :priority="$priority" />',
            ['binding' => CardBinding::of($card), 'heading' => 'h3', 'priority' => ImagePriority::Lazy, ...$props]
        );
    }

    private function renderTemplate(): string
    {
        return Blade::render('<x-meilifacets::listing.card :binding="$binding" />', ['binding' => CardBinding::template()]);
    }
}

/**
 * The component reaches wp_kses() and the config, so it cannot be covered by the
 * pure unit suite. What it renders is markup a search engine and a browser read.
 */
uses(TestCase::class, RendersTheModuleViews::class, CardComponentSupport::class);

beforeEach(function (): void {
    $this->renderTheModuleViews();
});

afterEach(function (): void {
    $this->restoreTheThemeViews();
});

it('hands an override the binding over its card', function (): void {
    $binding = new Card(CardBinding::of(['brand' => '<b>Acme</b>', 'id' => 12]))->binding;
    $brand = $binding->text('brand');

    expect((string) $brand->attributes)->toBe('');
    expect($brand->toHtml())->toBe('&lt;b&gt;Acme&lt;/b&gt;');
    expect((string) $binding->attributes(['data-product_id' => 'id']))->toBe('data-product_id="12"');
});

it('binds only the template', function (): void {
    $template = CardBinding::template();

    expect((string) $template->text('brand')->attributes)->toBe('data-meili-text="brand"');
    expect((string) $template->attributes(['data-product_id' => 'id']))->toBe('data-meili-attr="data-product_id:id"');
});

/** The price is what WooCommerce formatted: it is rendered as it stands. */
it('renders the price markup as it stands', function (): void {
    $price = '<del aria-hidden="true"><span class="amount">50,00</span></del>'
        .'<span class="screen-reader-text">Le prix initial était : 50,00.</span>';

    expect($this->render([CardField::Price->value => $price]))->toContain($price);
});

it('keeps the price markup woocommerce formats', function (): void {
    $price = '<del><span class="amount"><bdi>42,00</bdi></span></del>'
        .'<ins><span class="amount"><bdi>21,00</bdi></span></ins>';

    expect($this->render([CardField::Price->value => $price]))->toContain($price);
});

it('reserves the image space before any stylesheet loads', function (): void {
    $html = $this->render([
        CardField::ImageUrl->value => 'https://example.test/photo.jpg',
        CardField::ImageWidth->value => 300,
        CardField::ImageHeight->value => 200,
    ]);

    expect($html)->toContain('width="300"');
    expect($html)->toContain('height="200"');
});

it('leaves out an image the card does not have', function (): void {
    expect($this->document($this->render([]))->querySelector('img'))->toBeNull();
});

it('offers the browser the sizes the index holds', function (): void {
    $image = $this->image([
        CardField::ImageUrl->value => 'https://example.test/photo-300x300.jpg',
        CardField::ImageSrcset->value => self::SRCSET,
        CardField::ImageSizes->value => '(max-width: 300px) 100vw, 300px',
    ]);

    expect($image->getAttribute('srcset'))->toBe(self::SRCSET);
    expect($image->getAttribute('sizes'))->toBe('(max-width: 300px) 100vw, 300px');
});

it('names an image after the title when its alt text is empty', function (): void {
    $image = $this->image([
        CardField::Title->value => 'Serum',
        CardField::ImageUrl->value => 'https://example.test/photo.jpg',
        CardField::ImageAlt->value => '',
    ]);

    expect($image->getAttribute('alt'))->toBe('Serum');
});

it('renders the loading hints of the priority it is given', function (): void {
    $card = [CardField::ImageUrl->value => 'https://example.test/photo.jpg'];
    $eager = $this->render($card, ['priority' => ImagePriority::Eager]);

    expect($eager)->toContain('loading="eager"');
    expect($eager)->toContain('fetchpriority="high"');
    expect($this->render($card))->toContain('loading="lazy"');
});

/** A card the client clones lands in a page already painted: eager buys nothing. */
it('defers the image of a card nobody placed', function (): void {
    expect($this->renderTemplate())->toContain('loading="lazy"');
});

test('its template holds every hook the client fills', function (): void {
    $html = $this->renderTemplate();

    foreach (['url', 'image', 'title', 'price'] as $hook) {
        expect($html)->toContain('data-meili="'.$hook.'"');
    }
});

it('leaves out what a card has nothing to show in', function (): void {
    $card = $this->document($this->render([CardField::Url->value => 'https://example.test/serum']));

    foreach (['image', 'title', 'price'] as $hook) {
        $this->assertNull($card->querySelector('[data-meili="'.$hook.'"]'), $hook);
    }
});

test('a standalone card writes no hook', function (): void {
    $html = Blade::render('<x-meilifacets::listing.card :binding="$binding" />', ['binding' => CardBinding::standalone([
        CardField::Url->value => 'https://example.test/serum',
        CardField::ImageUrl->value => 'https://example.test/photo.jpg',
        CardField::Title->value => 'Serum',
        CardField::Price->value => '42,00',
    ])]);

    $card = $this->document($html);

    expect($card->querySelector('.meilifacetsCardTitle')?->textContent)->toBe('Serum');
    expect($card->querySelector('[data-meili]'))->toBeNull();
});

it('takes the heading level its context needs', function (): void {
    $card = $this->document($this->render([CardField::Title->value => 'Serum'], ['heading' => 'h4']));

    expect($card->querySelector('h4.meilifacetsCardTitle[data-meili="title"]')?->textContent)->toBe('Serum');
});

it('refuses a heading that would open any other tag', function (): void {
    $this->expectException(ViewException::class);
    $this->expectExceptionMessageMatches('/not a valid backing value/');

    Blade::render(
        '<x-meilifacets::listing.card :binding="$binding" :heading="$heading" />',
        ['binding' => CardBinding::of([]), 'heading' => 'script src=x']
    );
});

it('merges the classes the caller adds', function (): void {
    $html = Blade::render(
        '<x-meilifacets::listing.card :binding="$binding" class="col-span-2" />',
        ['binding' => CardBinding::of([])]
    );

    expect($html)->toContain('class="meilifacetsCard col-span-2"');
});
