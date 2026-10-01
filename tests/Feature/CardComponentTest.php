<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\View\Components\Listing\Card;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The component reaches wp_kses() and the config, so it cannot be covered by the
 * pure unit suite. What it renders is markup a search engine and a browser read.
 */
final class CardComponentTest extends TestCase
{
    private const string SRCSET = 'https://example.test/photo-300x300.jpg 300w, https://example.test/photo.jpg 600w';

    use RendersTheModuleViews;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderTheModuleViews();
    }

    protected function tearDown(): void
    {
        $this->restoreTheThemeViews();

        parent::tearDown();
    }

    #[Test]
    public function it_hands_an_override_the_binding_over_its_card(): void
    {
        $binding = new Card(['brand' => '<b>Acme</b>', 'id' => 12])->binding;
        $brand = $binding->text('brand');

        $this->assertSame('data-meili-text="brand"', (string) $brand->attributes);
        $this->assertSame('&lt;b&gt;Acme&lt;/b&gt;', $brand->toHtml());
        $this->assertSame(
            'data-product_id="12" data-meili-attr="data-product_id:id"',
            (string) $binding->attributes(['data-product_id' => 'id'])
        );
    }

    /** The price is what WooCommerce formatted: it is rendered as it stands. */
    #[Test]
    public function it_renders_the_price_markup_as_it_stands(): void
    {
        $price = '<del aria-hidden="true"><span class="amount">50,00</span></del>'
            .'<span class="screen-reader-text">Le prix initial était : 50,00.</span>';

        $this->assertStringContainsString($price, $this->render([CardField::Price->value => $price]));
    }

    #[Test]
    public function it_keeps_the_price_markup_woocommerce_formats(): void
    {
        $price = '<del><span class="amount"><bdi>42,00</bdi></span></del>'
            .'<ins><span class="amount"><bdi>21,00</bdi></span></ins>';

        $this->assertStringContainsString($price, $this->render([CardField::Price->value => $price]));
    }

    #[Test]
    public function it_reserves_the_image_space_before_any_stylesheet_loads(): void
    {
        $html = $this->render([
            CardField::ImageUrl->value => 'https://example.test/photo.jpg',
            CardField::ImageWidth->value => 300,
            CardField::ImageHeight->value => 200,
        ]);

        $this->assertStringContainsString('width="300"', $html);
        $this->assertStringContainsString('height="200"', $html);
    }

    #[Test]
    public function it_leaves_out_an_image_the_card_does_not_have(): void
    {
        $this->assertNull($this->document($this->render([]))->querySelector('img'));
    }

    #[Test]
    public function it_offers_the_browser_the_sizes_the_index_holds(): void
    {
        $image = $this->image([
            CardField::ImageUrl->value => 'https://example.test/photo-300x300.jpg',
            CardField::ImageSrcset->value => self::SRCSET,
            CardField::ImageSizes->value => '(max-width: 300px) 100vw, 300px',
        ]);

        $this->assertSame(self::SRCSET, $image->getAttribute('srcset'));
        $this->assertSame('(max-width: 300px) 100vw, 300px', $image->getAttribute('sizes'));
    }

    #[Test]
    public function it_names_an_image_after_the_title_when_its_alt_text_is_empty(): void
    {
        $image = $this->image([
            CardField::Title->value => 'Serum',
            CardField::ImageUrl->value => 'https://example.test/photo.jpg',
            CardField::ImageAlt->value => '',
        ]);

        $this->assertSame('Serum', $image->getAttribute('alt'));
    }

    #[Test]
    public function it_renders_the_loading_hints_of_the_priority_it_is_given(): void
    {
        $card = [CardField::ImageUrl->value => 'https://example.test/photo.jpg'];
        $eager = $this->render($card, ['priority' => ImagePriority::Eager]);

        $this->assertStringContainsString('loading="eager"', $eager);
        $this->assertStringContainsString('fetchpriority="high"', $eager);
        $this->assertStringContainsString('loading="lazy"', $this->render($card));
    }

    /** A card the client clones lands in a page already painted: eager buys nothing. */
    #[Test]
    public function it_defers_the_image_of_a_card_nobody_placed(): void
    {
        $this->assertStringContainsString('loading="lazy"', Blade::render('<x-meilifacets::listing.card-template />'));
    }

    #[Test]
    public function its_template_holds_every_hook_the_client_fills(): void
    {
        $html = Blade::render('<x-meilifacets::listing.card-template />');

        foreach (['url', 'image', 'title', 'price'] as $hook) {
            $this->assertStringContainsString('data-meili="'.$hook.'"', $html);
        }
    }

    #[Test]
    public function it_leaves_out_what_a_card_has_nothing_to_show_in(): void
    {
        $card = $this->document($this->render([CardField::Url->value => 'https://example.test/serum']));

        foreach (['image', 'title', 'price'] as $hook) {
            $this->assertNull($card->querySelector('[data-meili="'.$hook.'"]'), $hook);
        }
    }

    #[Test]
    public function it_takes_the_heading_level_its_context_needs(): void
    {
        $card = $this->document($this->render([CardField::Title->value => 'Serum'], ['heading' => 'h4']));

        $this->assertSame('Serum', $card->querySelector('h4.meilifacetsCardTitle[data-meili="title"]')?->textContent);
    }

    #[Test]
    public function it_refuses_a_heading_that_would_open_any_other_tag(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/not a valid backing value/');

        Blade::render(
            '<x-meilifacets::listing.card :card="[]" :heading="$heading" />',
            ['heading' => 'script src=x']
        );
    }

    #[Test]
    public function it_merges_the_classes_the_caller_adds(): void
    {
        $html = Blade::render('<x-meilifacets::listing.card :card="[]" class="col-span-2" />');

        $this->assertStringContainsString('class="meilifacetsCard col-span-2"', $html);
    }

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
            '<x-meilifacets::listing.card :card="$card" :heading="$heading" :priority="$priority" />',
            ['card' => $card, 'heading' => 'h3', 'priority' => ImagePriority::Lazy, ...$props]
        );
    }
}
