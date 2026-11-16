<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\DynamicComponent;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\Http\CurrentListing;
use Modules\MeiliFacets\Listing\Pagination;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Seo\ItemList;
use Modules\MeiliFacets\Tests\Feature\Concerns\RendersTheModuleViews;
use Modules\MeiliFacets\Tests\Unit\Doubles\ProjectListingCard;
use Modules\MeiliFacets\View\CardBinding;
use Modules\MeiliFacets\View\Components\Listing\Card;
use Tests\TestCase;

trait ResultsComponentHelpers
{
    /**
     * @return list<string>
     */
    private function shownReasons(string $html): array
    {
        preg_match_all('/<span data-meili="(no-results|past-the-end)">/', $html, $found);

        return $found[1];
    }

    /**
     * @return array<string, string>
     */
    private function card(string $title): array
    {
        return [
            CardField::Title->value => $title,
            CardField::Url->value => 'https://example.test/'.strtolower($title),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     */
    private function render(
        bool $failed = false,
        array $cards = [],
        ?ItemList $items = null,
        bool $pastTheEnd = false,
        string $cardComponent = Card::COMPONENT,
    ): string {
        $resolved = new readonly class($failed, $pastTheEnd)
        {
            public function __construct(private bool $failed, private bool $pastTheEnd) {}

            public function failed(): bool
            {
                return $this->failed;
            }

            public function pagination(): Pagination
            {
                return $this->pastTheEnd
                    ? new Pagination(9, 10, 40, 1000)
                    : new Pagination(1, 10, 0, 1000);
            }
        };

        // Blade leaves a run of spaces where a conditional attribute was.
        return (string) preg_replace('/\s+/', ' ', view('meilifacets::components.listing.results', [
            'listing' => $resolved,
            'cards' => $cards,
            'items' => $items,
            'hook' => fn (string $name) => Hook::from($name)->attribute(),
            'priority' => fn (): ImagePriority => ImagePriority::Eager,
            'cardComponent' => $cardComponent,
            'bindingOf' => CardBinding::of(...),
            'templateBinding' => CardBinding::template(...),
            'templatePriority' => fn (): ImagePriority => ImagePriority::Lazy,
        ])->render());
    }

    /** `<x-dynamic-component>` keeps the aliases known at its first use in the process. */
    private function forgetDynamicComponentAliases(): void
    {
        (static function (): void {
            self::$compiler = null;
            self::$componentClasses = [];
        })->bindTo(null, DynamicComponent::class)();
    }
}

/** `$items` is null on a page robots are told to skip — the state the view must not assume. */
uses(TestCase::class, RendersTheModuleViews::class, ResultsComponentHelpers::class);

it('the cards it renders on the server carry their hooks', function (): void {
    $this->renderTheModuleViews();
    $this->app->forgetScopedInstances();

    try {
        if ($this->app->make(CurrentListing::class)->named(ProductListing::NAME)->cards() === []) {
            $this->markTestSkipped('The catalogue lists no card.');
        }

        $html = Blade::render('<x-meilifacets::listing.results :name="$name" />', ['name' => ProductListing::NAME]);
    } finally {
        $this->restoreTheThemeViews();
        $this->app->forgetScopedInstances();
    }

    $card = HTMLDocument::createFromString('<!doctype html><body>'.$html, LIBXML_NOERROR)
        ->querySelector('[data-meili="results"] > [data-meili="card"]');

    expect($card)->toBeInstanceOf(Element::class);
    expect($card->querySelector('[data-meili="url"]'))->not->toBeNull();
});

/** Styled without bullets, a list loses its role in Safari with VoiceOver: `role="list"` gives it back. */
it('keeps the results a list without their bullets', function (): void {
    expect($this->render())->toContain('<ul class="meilifacetsResults" role="list"');
});

it('announces the outage instead of an empty list', function (): void {
    $html = $this->render(failed: true);

    expect($html)->toContain('meilifacetsUnavailable');
    expect($html)->not->toContain('<ul');
});

/** The client reveals the same message, in the site's language, when a later search fails. */
it('keeps the outage message hidden for the client', function (): void {
    $html = $this->render(cards: [$this->card('First')]);

    expect($html)->toMatch('/<div class="meilifacetsUnavailable" role="alert" hidden data-meili="unavailable"\s*>/');
    expect($html)->toContain(__('Search is temporarily unavailable. Please try again in a moment.'));
});

it('shows the outage message unhooked when the server render failed', function (): void {
    $html = $this->render(failed: true);

    expect($html)->not->toContain('data-meili="unavailable"');
    expect($html)->not->toContain(' hidden');
});

/** Kept for the client to fill, hidden so no screen reader counts an empty list. */
it('hides the list it has nothing to put in', function (): void {
    $html = $this->render(cards: []);

    expect($html)->toContain('<ul class="meilifacetsResults" role="list" hidden');
    expect($html)->not->toContain('class="meilifacetsResultsEmpty" hidden');
});

it('lists the cards it was given', function (): void {
    $html = $this->render(cards: [$this->card('First'), $this->card('Second')]);

    expect($html)->toContain('First');
    expect($html)->toContain('Second');
    expect($html)->toContain('class="meilifacetsResultsEmpty" hidden');
});

it('reveals the reason nothing is listed', function (): void {
    expect($this->shownReasons($this->render(cards: [])))->toBe(['no-results']);
    expect($this->shownReasons($this->render(cards: [], pastTheEnd: true)))->toBe(['past-the-end']);
});

/** Both are rendered: the client reveals one, it writes no text of its own. */
it('renders both reasons for the client', function (): void {
    preg_match_all('/data-meili="(?:no-results|past-the-end)">([^<]*)</', $this->render(cards: []), $found);
    [$nothingAtAll, $nothingHere] = array_map(trim(...), $found[1]);

    // Whatever language the site runs in, the two must not read the same.
    expect($nothingAtAll)->not->toBe('');
    expect($nothingHere)->not->toBe($nothingAtAll);
});

/** One clone source, so the client never carries markup of its own. */
it('offers a card template the client can clone', function (): void {
    $html = $this->render(cards: [$this->card('First')]);

    expect($html)->toContain('<template data-meili="card-template">');
    expect(substr_count($html, 'data-meili="card"'))->toBe(2);
});

it('a listing that names its card draws it on the server and in the template', function (): void {
    $aliases = Blade::getClassComponentAliases();
    Blade::component(ProjectListingCard::class, ProjectListingCard::ALIAS);
    $this->forgetDynamicComponentAliases();

    try {
        $html = $this->render(cards: [$this->card('First')], cardComponent: ProjectListingCard::ALIAS);
    } finally {
        (fn (): array => $this->classComponentAliases = $aliases)->call(Blade::getFacadeRoot());
        $this->forgetDynamicComponentAliases();
    }

    expect($html)->toContain('<p class="probeListingCard" data-priority="eager" data-meili="title">First</p>');
    expect($html)->toContain('<p class="probeListingCard" data-priority="lazy" data-meili="title" data-meili-text="title"></p>');
    expect($html)->not->toContain('meilifacetsCard');
});

/** No template on an outage: the contract fails and the client stays out. */
it('offers nothing to clone when the search is down', function (): void {
    expect($this->render(failed: true))->not->toContain('card-template');
});

it('publishes the structured list', function (): void {
    $html = $this->render(cards: [$this->card('First')], items: new ItemList([$this->card('First')]));

    expect($html)->toContain('application/ld+json');
    expect($html)->toContain('"@type":"ItemList"');
});

it('survives a page that publishes no structured list', function (): void {
    $html = $this->render(cards: [$this->card('First')], items: null);

    expect($html)->toContain('meilifacetsResultsItem');
    expect($html)->not->toContain('application/ld+json');
});

it('writes no empty structured list', function (): void {
    $html = $this->render(cards: [$this->card('First')], items: new ItemList([]));

    expect($html)->not->toContain('application/ld+json');
});
