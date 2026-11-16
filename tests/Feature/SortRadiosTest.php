<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Tests\Feature\Concerns\FindsHooks;
use Modules\MeiliFacets\Tests\Feature\Concerns\SwitchesTheSiteLocale;
use Tests\TestCase;

trait SortRadiosSupport
{
    private const string COLLAPSIBLE = '<x-meilifacets::listing.sort name="products" widget="radios" collapsible />';

    /** A locale no catalogue ships, whose sentence puts the order first. */
    private const string VALUE_FIRST = 'xx';

    private function rendered(string $blade): HTMLDocument
    {
        return HTMLDocument::createFromString('<div>'.Blade::render($blade).'</div>', LIBXML_NOERROR);
    }

    /** A sort's trigger holds no badge: its text is the name, less the label it hides from assistive technologies. */
    private function nameOf(Element $toggle): string
    {
        $named = $toggle->cloneNode(true);

        foreach ($named->querySelectorAll('[aria-hidden="true"]') as $hidden) {
            $hidden->remove();
        }

        return trim($named->textContent);
    }
}

/** Step 4b: the sort drawn as radios, in a section like a facet's (C-4). */
uses(TestCase::class, FindsHooks::class, SwitchesTheSiteLocale::class, SortRadiosSupport::class);

beforeEach(function (): void {
    $this->app->forgetScopedInstances();
});

afterEach(function (): void {
    request()->query->replace([]);
    $this->app->forgetScopedInstances();
});

/** Styled without bullets, a list loses its role in Safari with VoiceOver: `role="list"` gives it back. */
it('keeps the sorts a list without their bullets', function (): void {
    $sorts = $this->rendered('<x-meilifacets::listing.sort name="products" widget="radios" />')->querySelector('.meilifacetsFacetValues');

    expect($sorts?->getAttribute('role'))->toBe('list');
});

it('draws one radio per sort with the one in force checked', function (): void {
    $sorts = $this->app->make(CurrentListing::class)->named(ProductListing::NAME)->sorts();
    $choices = $this->rendered('<x-meilifacets::listing.sort name="products" widget="radios" />')->querySelectorAll($this->hooked(Hook::SortChoice));

    expect($choices)->toHaveCount(count($sorts) + 1);
    expect($choices[0]->getAttribute('value'))->toBe('');
    expect($choices[0]->hasAttribute('checked'))->toBeTrue();
    expect(array_values(array_unique(array_map(static fn ($choice): string => $choice->getAttribute('type'), iterator_to_array($choices)))))->toBe(['radio']);
});

/** The client hides a row and names the order in force through these, never through a tag. */
it('hooks each choice row and publishes each label', function (): void {
    $sorts = $this->app->make(CurrentListing::class)->named(ProductListing::NAME)->sorts();
    $rows = $this->rendered('<x-meilifacets::listing.sort name="products" widget="radios" />')->querySelectorAll($this->hooked(Hook::SortChoiceRow));

    expect($rows)->toHaveCount(count($sorts) + 1);

    foreach ($rows as $row) {
        $choice = $row->querySelector($this->hooked(Hook::SortChoice));

        expect($choice->getAttribute('data-label'))->toBe(trim($row->textContent));
    }
});

it('names the group and closes it behind a trigger when collapsible', function (): void {
    $document = $this->rendered('<x-meilifacets::listing.sort name="products" widget="radios" collapsible />');
    $group = $document->querySelector($this->hooked(Hook::SortChoices));
    $toggle = $group->querySelector('legend > '.$this->hooked(Hook::Toggle));
    $panel = $document->getElementById($toggle->getAttribute('aria-controls'));

    expect(strtolower($group->localName))->toBe('fieldset');
    expect(trim($toggle->querySelector('.meilifacetsFacetToggleLabel')->textContent))->toBe(__('Sort by'));
    expect($toggle->getAttribute('aria-expanded'))->toBe('false');
    expect($panel->matches($this->hooked(Hook::Panel)))->toBeTrue();
    expect($panel->hasAttribute('hidden'))->toBeTrue();
});

/** The trigger names the order in force, in its text: that text is its accessible name, label first. */
it('names the default order in its trigger', function (): void {
    $toggle = $this->underLocales('en', 'en_US', fn (): HTMLDocument => $this->rendered(self::COLLAPSIBLE))->querySelector($this->hooked(Hook::Toggle));

    expect($toggle->querySelector($this->hooked(Hook::SortChosen))->textContent)->toBe('Relevance');
    expect($this->nameOf($toggle))->toBe('Sort by: Relevance');
    expect($toggle->querySelector('.meilifacetsFacetToggleLabel')->textContent)->toBe('Sort by');
});

it('names the order the url asks for', function (): void {
    $value = array_key_first($this->app->make(CurrentListing::class)->named(ProductListing::NAME)->sorts());
    request()->query->replace(['sort' => $value]);
    $this->app->forgetScopedInstances();

    [$label, $toggle] = $this->underLocales('en', 'en_US', fn (): array => [
        $this->app->make(CurrentListing::class)->named(ProductListing::NAME)->sorts()[$value]->label,
        $this->rendered(self::COLLAPSIBLE)->querySelector($this->hooked(Hook::Toggle)),
    ]);

    expect($this->nameOf($toggle))->toBe('Sort by: '.$label);
});

/** French sets a no-break space before the colon, so the value never wraps away from its label. */
it('writes the french colon after a no break space', function (): void {
    $toggle = $this->underLocales('fr', 'fr_FR', fn (): HTMLDocument => $this->rendered(self::COLLAPSIBLE))->querySelector($this->hooked(Hook::Toggle));

    expect($this->nameOf($toggle))->toBe("Trier par\u{00A0}: Pertinence");
});

/** A language may put the order first: the sentence is cut where it puts it, never where English does. */
it('writes the order where the sentence puts it', function (): void {
    app('translator')->addLines(['*.Sort by: :choice' => ':choice — sort'], self::VALUE_FIRST);

    $toggle = $this->underLocales(self::VALUE_FIRST, self::VALUE_FIRST, fn (): HTMLDocument => $this->rendered(self::COLLAPSIBLE))->querySelector($this->hooked(Hook::Toggle));
    $chosen = $toggle->querySelector($this->hooked(Hook::SortChosen));

    expect($chosen->previousSibling?->textContent ?? '')->toBe('');
    expect($chosen->nextSibling?->textContent)->toBe(' — sort');
    expect($this->nameOf($toggle))->toBe('Relevance — sort');
});

/** The label stands alone in the section: the sentence says it again, so it is never read out twice. */
it('hides the label the sentence restates', function (): void {
    $toggle = $this->rendered(self::COLLAPSIBLE)->querySelector($this->hooked(Hook::Toggle));

    expect($toggle->querySelector('.meilifacetsFacetToggleLabel')->getAttribute('aria-hidden'))->toBe('true');
});

it('names the section by a label alone and shows no order', function (): void {
    $toggle = $this->rendered('<x-meilifacets::listing.sort name="products" widget="radios" collapsible label="Sort" />')
        ->querySelector($this->hooked(Hook::Toggle));
    $label = $toggle->querySelector('.meilifacetsFacetToggleLabel');

    expect($toggle->querySelector($this->hooked(Hook::SortChosen)))->toBeNull();
    expect($toggle->querySelector('.meilifacetsSortCaption'))->toBeNull();
    expect($label->textContent)->toBe('Sort');
    expect($label->hasAttribute('aria-hidden'))->toBeFalse();
    expect($this->nameOf($toggle))->toBe('Sort');
});

it('names a plain group by its label', function (): void {
    $legend = $this->rendered('<x-meilifacets::listing.sort name="products" widget="radios" label="Sort" />')
        ->querySelector($this->hooked(Hook::SortChoices).' > legend');

    expect(trim($legend->textContent))->toBe('Sort');
});

it('writes the order where a label puts it', function (): void {
    $toggle = $this->underLocales('en', 'en_US', fn (): HTMLDocument => $this->rendered(
        '<x-meilifacets::listing.sort name="products" widget="radios" collapsible label="Order — :choice" />'
    ))->querySelector($this->hooked(Hook::Toggle));

    expect($toggle->querySelector($this->hooked(Hook::SortChosen))->textContent)->toBe('Relevance');
    expect($this->nameOf($toggle))->toBe('Order — Relevance');
});

it('names the listbox by its label', function (): void {
    $label = $this->rendered('<x-meilifacets::listing.sort name="products" label="Sort" />')
        ->querySelector($this->hooked(Hook::Sort).' > label');

    expect(trim($label->textContent))->toBe('Sort');
});

it('names a plain group sort by when its label holds the order', function (): void {
    $document = $this->rendered('<x-meilifacets::listing.sort name="products" widget="radios" label="Order: :choice" />');

    expect(trim($document->querySelector($this->hooked(Hook::SortChoices).' > legend')->textContent))->toBe(__('Sort by'));
    $this->assertStringNotContainsString('Order:', $document->saveHtml());
});

it('names the listbox sort by when its label holds the order', function (): void {
    $document = $this->rendered('<x-meilifacets::listing.sort name="products" label="Order: :choice" />');

    expect(trim($document->querySelector($this->hooked(Hook::Sort).' > label')->textContent))->toBe(__('Sort by'));
    $this->assertStringNotContainsString('Order:', $document->saveHtml());
});

/** A sort is an order: its trigger has no count, and nothing it could be described by. */
it('gives its trigger no badge', function (): void {
    request()->query->replace(['sort' => array_key_first($this->app->make(CurrentListing::class)->named(ProductListing::NAME)->sorts())]);
    $this->app->forgetScopedInstances();

    $document = $this->rendered('<x-meilifacets::listing.sort name="products" widget="radios" collapsible />');

    expect($document->querySelector($this->hooked(Hook::SelectedCount)))->toBeNull();
    expect($document->querySelector($this->hooked(Hook::Toggle))->hasAttribute('aria-describedby'))->toBeFalse();
});

it('refuses a second sort whatever its widget', function (): void {
    $this->expectExceptionMessageMatches('/The sort of listing "[^"]+" is rendered twice/');

    Blade::render('<x-meilifacets::listing.sort name="products" /><x-meilifacets::listing.sort name="products" widget="radios" />');
});

/** The listbox stays the default, and the column renders as it did. */
it('draws the listbox unless asked', function (): void {
    $default = Blade::render('<x-meilifacets::listing.sort name="products" />');
    $this->app->forgetScopedInstances();

    expect($default)->toBe(Blade::render('<x-meilifacets::listing.sort name="products" widget="listbox" />'));
    expect($default)->toContain(Hook::SortTrigger->attribute()->toHtml());
    $this->assertStringNotContainsString(Hook::SortChoices->attribute()->toHtml(), $default);
});
