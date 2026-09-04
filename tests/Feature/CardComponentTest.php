<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\View\CardImage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The component reaches wp_kses() and the config, so it cannot be covered by the
 * pure unit suite. What it renders is markup a search engine and a browser read.
 */
final class CardComponentTest extends TestCase
{
    #[Test]
    public function it_strips_markup_the_price_has_no_business_carrying(): void
    {
        $html = $this->render([CardField::Price->value => '<span>42</span><script>alert(1)</script>']);

        $this->assertStringContainsString('<span>42</span>', $html);
        $this->assertStringNotContainsString('<script>', $html);
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
    public function it_loads_the_first_cards_eagerly_and_the_rest_lazily(): void
    {
        $this->assertStringContainsString('loading="eager"', $this->render([], ['rank' => 0]));
        $this->assertStringContainsString('fetchpriority="high"', $this->render([], ['rank' => 0]));
        $this->assertStringContainsString('loading="lazy"', $this->render([], ['rank' => 99]));
    }

    #[Test]
    public function it_takes_the_heading_level_its_context_needs(): void
    {
        $html = $this->render([CardField::Title->value => 'Serum'], ['heading' => 'h4']);

        $this->assertStringContainsString('<h4 class="meilifacetsCardTitle">Serum</h4>', $html);
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
            '<x-meilifacets::card :card="$card" :heading="$heading" :rank="$rank" />',
            ['card' => $card, 'heading' => 'h3', 'rank' => 0, ...$props]
        );
    }
}
