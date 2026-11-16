<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Tests\Feature\Concerns\FindsHooks;
use Modules\MeiliFacets\Tests\Feature\Concerns\HoldsCatalogueValues;
use Modules\MeiliFacets\Tests\Feature\Concerns\RendersTheModuleViews;
use Modules\MeiliFacets\Tests\Feature\Concerns\SwitchesApplyMode;
use Tests\TestCase;

trait DrawerFooterHelpers
{
    private mixed $applyMode;

    private function rendered(string $blade): HTMLDocument
    {
        return HTMLDocument::createFromString('<div>'.Blade::render($blade).'</div>', LIBXML_NOERROR);
    }
}

/** Step 5b: « Apply (X) » on its own, and « Clear all » drawn as an icon, for the footer of the drawer. */
uses(
    TestCase::class,
    FindsHooks::class,
    HoldsCatalogueValues::class,
    RendersTheModuleViews::class,
    SwitchesApplyMode::class,
    DrawerFooterHelpers::class,
);

beforeEach(function (): void {
    $this->applyMode = config('meilifacets.apply_mode');
    $this->app->forgetScopedInstances();
    $this->renderTheModuleViews();
});

/** The suite shares one application: a mode or an address left here would reach a later class. */
afterEach(function (): void {
    $this->restoreTheThemeViews();
    $this->useApplyMode($this->applyMode);
    request()->query->replace([]);
    $this->app->forgetScopedInstances();
});

it('renders apply with the count it would apply when the listing waits for it', function (): void {
    $this->useApplyMode('submit');
    $this->holdTwoValues();

    $apply = $this->rendered('<x-meilifacets::listing.apply name="products" />')->querySelector($this->hooked(Hook::Apply));
    $count = $apply->querySelector($this->hooked(Hook::ActiveCount));

    expect($apply->getAttribute('type'))->toBe('button');
    expect($apply->getAttribute('data-shape'))->toBe('pill');
    expect(trim($apply->firstChild->textContent))->toBe(__('Apply'));
    expect($count->textContent)->toBe('2');
    expect($apply->getAttribute('aria-describedby'))->toBe($count->getAttribute('id'));
    expect($count->getAttribute('aria-hidden'))->toBe('true');
});

/** C-1: in `immediate` the button is a landmark of the drawer, rendered only when asked. */
it('renders apply in immediate only when visible in drawer', function (): void {
    $this->useApplyMode('immediate');

    expect($this->rendered('<x-meilifacets::listing.apply name="products" />')->querySelector($this->hooked(Hook::Apply)))->toBeNull();
    expect($this->rendered('<x-meilifacets::listing.apply name="products" visible-in-drawer />')->querySelector($this->hooked(Hook::Apply)))->not->toBeNull();
});

/** In `immediate` every box searches already: the button only marks the way out of the sheet, and the sheet alone shows it. */
it('marks apply for the sheet only when the listing searches at once', function (): void {
    $this->useApplyMode('immediate');
    $immediate = $this->rendered('<x-meilifacets::listing.apply name="products" visible-in-drawer />')->querySelector($this->hooked(Hook::Apply));
    $this->app->forgetScopedInstances();
    $this->useApplyMode('submit');
    $submit = $this->rendered('<x-meilifacets::listing.apply name="products" visible-in-drawer />')->querySelector($this->hooked(Hook::Apply));

    expect($immediate->getAttribute('data-only'))->toBe('sheet');
    expect($submit->hasAttribute('data-only'))->toBeFalse();
});

it('hides and empties the count while nothing is held', function (): void {
    $this->useApplyMode('submit');

    $count = $this->rendered('<x-meilifacets::listing.apply name="products" />')->querySelector($this->hooked(Hook::ActiveCount));

    expect($count->hasAttribute('hidden'))->toBeTrue();
    expect($count->textContent)->toBe('');
});

it('the group hands its button over when asked', function (): void {
    $this->useApplyMode('submit');

    expect(Blade::render('<x-meilifacets::listing.facets name="products" :with-apply="false" />'))->not->toContain(Hook::Apply->attribute()->toHtml());
});

it('the group renders its button unless asked', function (): void {
    $this->useApplyMode('submit');
    $explicit = Blade::render('<x-meilifacets::listing.facets name="products" :with-apply="true" />');
    $this->app->forgetScopedInstances();

    expect(Blade::render('<x-meilifacets::listing.facets name="products" />'))->toBe($explicit);
    expect($explicit)->toContain(Hook::Apply->attribute()->toHtml());
});

/** The column keeps its look: a block named in words, with no count, through the one « Apply » component. */
it('the group renders the apply component as a block', function (): void {
    $this->useApplyMode('submit');
    $this->holdTwoValues();

    $group = $this->rendered('<x-meilifacets::listing.facets name="products" />')->querySelector($this->hooked(Hook::Facets));
    $applies = $group->querySelectorAll($this->hooked(Hook::Apply));
    $apply = $applies[0];

    expect($applies)->toHaveCount(1);
    expect($apply->getAttribute('class'))->toBe('meilifacetsApply');
    expect(trim($apply->textContent))->toBe(__('Apply filters'));
    expect($apply->hasAttribute('data-shape'))->toBeFalse();
    expect($apply->hasAttribute('aria-describedby'))->toBeFalse();
    expect($apply->querySelector($this->hooked(Hook::ActiveCount)))->toBeNull();
});

it('draws clear all as an icon named by its words', function (): void {
    $this->holdTwoValues();

    $reset = $this->rendered('<x-meilifacets::listing.reset name="products" shape="icon" />')->querySelector($this->hooked(Hook::Reset));
    $image = $reset->querySelector('img');

    expect($reset->getAttribute('aria-label'))->toBe(__('Clear all'));
    expect($reset->getAttribute('data-shape'))->toBe('icon');
    expect(trim($reset->textContent))->toBe('');
    expect($image->getAttribute('src'))->toEndWith('/modules/meilifacets/images/trash.svg');
    expect($image->getAttribute('alt'))->toBe('');
    expect([
        $image->getAttribute('width'), $image->getAttribute('height'), $image->getAttribute('loading'), $image->getAttribute('fetchpriority'),
    ])->toBe(['20', '20', 'lazy', 'low']);
});

it('takes the icon a theme gives and none when the slot is empty', function (): void {
    $given = $this->rendered('<x-meilifacets::listing.reset name="products" shape="icon"><x-slot:icon><svg id="bin"></svg></x-slot:icon></x-meilifacets::listing.reset>')
        ->querySelector($this->hooked(Hook::Reset));
    $this->app->forgetScopedInstances();
    $emptied = $this->rendered('<x-meilifacets::listing.reset name="products" shape="icon"><x-slot:icon></x-slot:icon></x-meilifacets::listing.reset>')
        ->querySelector($this->hooked(Hook::Reset));

    expect($given->querySelector('svg#bin'))->not->toBeNull();
    expect($given->querySelector('img'))->toBeNull();
    expect($given->firstElementChild->getAttribute('aria-hidden'))->toBe('true');
    expect($emptied->childElementCount)->toBe(0);
    expect($emptied->getAttribute('aria-label'))->toBe(__('Clear all'));
});

/** The text variant is the default, and it renders as it always has. */
it('renders the text reset unchanged without a shape', function (): void {
    $reset = $this->rendered('<x-meilifacets::listing.reset name="products" />')->querySelector($this->hooked(Hook::Reset));

    expect(trim($reset->textContent))->toBe(__('Clear all'));
    expect($reset->hasAttribute('aria-label'))->toBeFalse();
    expect($reset->hasAttribute('data-shape'))->toBeFalse();
});

/** A variant beside the default, never in its place. */
it('marks the pill reset and only it', function (): void {
    $pill = $this->rendered('<x-meilifacets::listing.reset name="products" shape="pill" />')->querySelector($this->hooked(Hook::Reset));
    $text = $this->rendered('<x-meilifacets::listing.reset name="products" shape="text" />')->querySelector($this->hooked(Hook::Reset));

    expect($pill->getAttribute('data-shape'))->toBe('pill');
    expect(trim($pill->textContent))->toBe(__('Clear all'));
    expect($text->hasAttribute('data-shape'))->toBeFalse();
});

it('points at a trash icon that was published', function (): void {
    $published = public_path('modules/meilifacets/images/trash.svg');

    $this->assertFileExists($published);
    $this->assertFileEquals(module_path('MeiliFacets', 'resources/assets/images/trash.svg'), $published);
});
