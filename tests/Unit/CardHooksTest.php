<?php

declare(strict_types=1);

use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\View\CardBinding;
use Modules\MeiliFacets\View\CardHooks;

/** What a card template writes for each field it shows, listed and standalone. */
const CARD_HOOKS_CARD = [
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

it('writes the link with the hook the client addresses a listed card by', function (): void {
    $link = new CardHooks(CardBinding::of(CARD_HOOKS_CARD))->link();

    expect($link->isPresent())->toBeTrue();
    expect($link->attributes->get('href'))->toBe('https://example.test/red');
    expect($link->attributes->get('data-meili'))->toBe('url');
});

it('leaves the hook off a standalone card the client never rebuilds', function (): void {
    $link = new CardHooks(CardBinding::standalone(CARD_HOOKS_CARD))->link();

    expect($link->attributes->get('href'))->toBe('https://example.test/red');
    expect($link->attributes->get('data-meili'))->toBeNull();
});

it('writes the image with its candidates and its loading priority', function (): void {
    $image = new CardHooks(CardBinding::of(CARD_HOOKS_CARD))->image(ImagePriority::Eager);

    expect($image->attributes->get('src'))->toBe('https://example.test/red.jpg');
    expect($image->attributes->get('srcset'))->toBe('red-300.jpg 300w');
    expect($image->attributes->get('alt'))->toBe('Red shoe');
    expect($image->attributes->get('loading'))->toBe('eager');
    expect($image->attributes->get('fetchpriority'))->toBe('high');
    expect($image->attributes->get('data-meili'))->toBe('image');
});

it('writes a thumbnail without candidates or priority', function (): void {
    $thumbnail = new CardHooks(CardBinding::of(CARD_HOOKS_CARD))->thumbnail();

    expect($thumbnail->attributes->get('src'))->toBe('https://example.test/red.jpg');
    expect((string) $thumbnail->attributes->get('width'))->toBe('300');
    expect($thumbnail->attributes->get('srcset'))->toBeNull();
    expect($thumbnail->attributes->get('loading'))->toBeNull();
});

it('leaves out the image of a card without one', function (): void {
    $hooks = new CardHooks(CardBinding::standalone(['title' => 'No photo']));

    expect($hooks->image(ImagePriority::Lazy)->isPresent())->toBeFalse();
    expect($hooks->thumbnail()->isPresent())->toBeFalse();
});

it('escapes the text fields and writes the price as the markup it is', function (): void {
    $hooks = new CardHooks(CardBinding::of(CARD_HOOKS_CARD));

    expect($hooks->title()->toHtml())->toBe('Red shoe');
    expect($hooks->summary()->toHtml())->toBe('Soft &amp; warm');
    expect($hooks->price()->toHtml())->toBe('<span class="amount">10 €</span>');
    expect($hooks->summary()->attributes->get('data-meili'))->toBe('summary');
});

it('leaves out the price a card does not carry but keeps it in a template', function (): void {
    expect(new CardHooks(CardBinding::of(['title' => 'Free']))->price()->isPresent())->toBeFalse();
    expect(new CardHooks(CardBinding::template())->price()->isPresent())->toBeTrue();
});

/** The browser rebuilds a listed card's image from these: a missing one stays the previous product's. */
it('writes every attribute the layout of an image needs, on the image and the thumbnail', function (): void {
    $hooks = new CardHooks(CardBinding::of(CARD_HOOKS_CARD));
    $image = $hooks->image(ImagePriority::Eager)->attributes;
    $thumbnail = $hooks->thumbnail()->attributes;

    expect([$image->get('sizes'), (string) $image->get('width'), (string) $image->get('height')])->toBe(['300px', '300', '200'])
        ->and([$thumbnail->get('src'), (string) $thumbnail->get('height'), $thumbnail->get('data-meili')])
        ->toBe(['https://example.test/red.jpg', '200', 'image']);
});
