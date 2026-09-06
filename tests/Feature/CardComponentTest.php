<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\View\CardImage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The component reaches wp_kses() and the config, so it cannot be covered by the
 * pure unit suite. What it renders is markup a search engine and a browser read.
 */
final class CardComponentTest extends TestCase
{
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
    public function it_never_renders_an_image_without_a_src(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('src="'.CardImage::BLANK.'"', $html);
        $this->assertStringNotContainsString('src=""', $html);
    }

    #[Test]
    public function it_renders_the_loading_hints_of_the_priority_it_is_given(): void
    {
        $eager = $this->render([], ['priority' => ImagePriority::Eager]);

        $this->assertStringContainsString('loading="eager"', $eager);
        $this->assertStringContainsString('fetchpriority="high"', $eager);
        $this->assertStringContainsString('loading="lazy"', $this->render([]));
    }

    /** A card the client clones lands in a page already painted: eager buys nothing. */
    #[Test]
    public function it_defers_the_image_of_a_card_nobody_placed(): void
    {
        $this->assertStringContainsString('loading="lazy"', Blade::render('<x-meilifacets::card :card="[]" />'));
    }

    #[Test]
    public function it_marks_every_value_the_client_repaints(): void
    {
        $html = $this->render([]);

        foreach (['url', 'image', 'title', 'price'] as $hook) {
            $this->assertStringContainsString('data-meili="'.$hook.'"', $html);
        }
    }

    #[Test]
    public function it_takes_the_heading_level_its_context_needs(): void
    {
        $html = $this->render([CardField::Title->value => 'Serum'], ['heading' => 'h4']);

        $this->assertStringContainsString('<h4 class="meilifacetsCardTitle" data-meili="title">Serum</h4>', $html);
    }

    #[Test]
    public function it_refuses_a_heading_that_would_open_any_other_tag(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessageMatches('/not a valid backing value/');

        Blade::render(
            '<x-meilifacets::card :card="[]" :heading="$heading" />',
            ['heading' => 'script src=x']
        );
    }

    #[Test]
    public function it_merges_the_classes_the_caller_adds(): void
    {
        $html = Blade::render('<x-meilifacets::card :card="[]" class="col-span-2" />');

        $this->assertStringContainsString('class="meilifacetsCard col-span-2"', $html);
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  array<string, mixed>  $props
     */
    private function render(array $card, array $props = []): string
    {
        return Blade::render(
            '<x-meilifacets::card :card="$card" :heading="$heading" :priority="$priority" />',
            ['card' => $card, 'heading' => 'h3', 'priority' => ImagePriority::Lazy, ...$props]
        );
    }
}
