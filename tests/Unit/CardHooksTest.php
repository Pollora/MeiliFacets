<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\View\CardBinding;
use Modules\MeiliFacets\View\CardHooks;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** What a card template writes for each field it shows, listed and standalone. */
final class CardHooksTest extends TestCase
{
    private const array CARD = [
        'title' => 'Red shoe',
        'url' => 'https://example.test/red',
        'summary' => 'Soft & warm',
        'price' => '<span class="amount">10 €</span>',
        'image_url' => 'https://example.test/red.jpg',
        'image_srcset' => 'red-300.jpg 300w',
        'image_sizes' => '300px',
        'image_alt' => '',
        'image_width' => 300,
        'image_height' => 200,
    ];

    #[Test]
    public function it_writes_the_link_with_the_hook_the_client_addresses_a_listed_card_by(): void
    {
        $link = new CardHooks(CardBinding::of(self::CARD))->link();

        $this->assertTrue($link->isPresent());
        $this->assertSame('https://example.test/red', $link->attributes->get('href'));
        $this->assertSame('url', $link->attributes->get('data-meili'));
    }

    #[Test]
    public function it_leaves_the_hook_off_a_standalone_card_the_client_never_rebuilds(): void
    {
        $link = new CardHooks(CardBinding::standalone(self::CARD))->link();

        $this->assertSame('https://example.test/red', $link->attributes->get('href'));
        $this->assertNull($link->attributes->get('data-meili'));
    }

    #[Test]
    public function it_writes_the_image_with_its_candidates_and_its_loading_priority(): void
    {
        $image = new CardHooks(CardBinding::of(self::CARD))->image(ImagePriority::Eager);

        $this->assertSame('https://example.test/red.jpg', $image->attributes->get('src'));
        $this->assertSame('red-300.jpg 300w', $image->attributes->get('srcset'));
        $this->assertSame('Red shoe', $image->attributes->get('alt'));
        $this->assertSame('eager', $image->attributes->get('loading'));
        $this->assertSame('high', $image->attributes->get('fetchpriority'));
        $this->assertSame('image', $image->attributes->get('data-meili'));
    }

    #[Test]
    public function it_writes_a_thumbnail_without_candidates_or_priority(): void
    {
        $thumbnail = new CardHooks(CardBinding::of(self::CARD))->thumbnail();

        $this->assertSame('https://example.test/red.jpg', $thumbnail->attributes->get('src'));
        $this->assertSame('300', (string) $thumbnail->attributes->get('width'));
        $this->assertNull($thumbnail->attributes->get('srcset'));
        $this->assertNull($thumbnail->attributes->get('loading'));
    }

    #[Test]
    public function it_leaves_out_the_image_of_a_card_without_one(): void
    {
        $hooks = new CardHooks(CardBinding::standalone(['title' => 'No photo']));

        $this->assertFalse($hooks->image(ImagePriority::Lazy)->isPresent());
        $this->assertFalse($hooks->thumbnail()->isPresent());
    }

    #[Test]
    public function it_escapes_the_text_fields_and_writes_the_price_as_the_markup_it_is(): void
    {
        $hooks = new CardHooks(CardBinding::of(self::CARD));

        $this->assertSame('Red shoe', $hooks->title()->toHtml());
        $this->assertSame('Soft &amp; warm', $hooks->summary()->toHtml());
        $this->assertSame('<span class="amount">10 €</span>', $hooks->price()->toHtml());
        $this->assertSame('summary', $hooks->summary()->attributes->get('data-meili'));
    }

    #[Test]
    public function it_leaves_out_the_price_a_card_does_not_carry_but_keeps_it_in_a_template(): void
    {
        $this->assertFalse(new CardHooks(CardBinding::of(['title' => 'Free']))->price()->isPresent());
        $this->assertTrue(new CardHooks(CardBinding::template())->price()->isPresent());
    }
}
