<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\DynamicComponent;
use Illuminate\View\ViewException;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\SiteSearch\AcceptedSearchTypes;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\SiteSearch\WooCommerceSearchableTypes;
use Modules\MeiliFacets\Tests\Feature\Concerns\PinsIndexedPostTypes;
use Modules\MeiliFacets\Tests\Unit\Doubles\ProjectSearchCard;
use Tests\TestCase;

trait SearchCompositionHelpers
{
    private const string NOTE = 'meili_test_note';

    /**
     * @return list<string>
     */
    private function acceptedTypes(): array
    {
        return array_keys($this->app->make(AcceptedSearchTypes::class)->all());
    }

    private function rendered(string $template): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8"?><body>'.Blade::render($template).'</body>');

        return new DOMXPath($document);
    }

    /**
     * @return list<DOMElement>
     */
    private function hooked(DOMXPath|DOMElement $scope, string $hook): array
    {
        [$xpath, $context] = $scope instanceof DOMXPath ? [$scope, null] : [new DOMXPath($scope->ownerDocument), $scope];
        $found = $xpath->query('.//*[@data-meili="'.$hook.'"]', $context ?? $xpath->document->documentElement);

        return array_values(array_filter(iterator_to_array($found ?: []), static fn ($node): bool => $node instanceof DOMElement));
    }

    /**
     * @return list<string>
     */
    private function childBricks(DOMXPath $page, DOMElement $parent): array
    {
        return array_map(
            static fn (DOMElement $brick): string => $brick->getAttribute('data-meili').($brick->hasAttribute('data-type') ? ':'.$brick->getAttribute('data-type') : ''),
            array_values(array_filter(iterator_to_array($page->query('./*[@data-meili]', $parent) ?: []), static fn ($node): bool => $node instanceof DOMElement)),
        );
    }

    /**
     * @return list<string>
     */
    private function sectionTypes(DOMXPath $page): array
    {
        return array_map(static fn (DOMElement $section): string => $section->getAttribute('data-type'), $this->hooked($page, 'search-section'));
    }

    private function section(DOMXPath $page, string $postType): DOMElement
    {
        $sections = array_filter($this->hooked($page, 'search-section'), static fn (DOMElement $section): bool => $section->getAttribute('data-type') === $postType);

        $this->assertCount(1, $sections, $postType);

        return reset($sections);
    }

    private function templateOf(DOMXPath $page, string $postType): string
    {
        return $this->html($this->hooked($this->section($page, $postType), 'search-card-template')[0]);
    }

    private function html(DOMElement $element): string
    {
        return (string) $element->ownerDocument?->saveHTML($element);
    }
}

/** The bricks of a search root, as the module composes them and as a theme does. */
uses(TestCase::class, PinsIndexedPostTypes::class, SearchCompositionHelpers::class);

beforeEach(function (): void {
    $this->pinIndexedPostTypes();
});

/** The registry and the declarations are scoped: what one test renders would reach the next. */
afterEach(function (): void {
    $this->unpinIndexedPostTypes();
    unregister_post_type(self::NOTE);
    $this->app->forgetScopedInstances();
});

it('composes every brick itself when the template hands it nothing', function (): void {
    $page = $this->rendered('<x-meilifacets::search />');

    foreach (['search-toggle', 'search-panel', 'search-input', 'search-empty', 'search-unavailable', 'search-status'] as $hook) {
        $this->assertCount(1, $this->hooked($page, $hook), $hook);
    }

    expect($this->sectionTypes($page))->toBe($this->acceptedTypes());
});

it('opens the panel it renders and keeps both closed', function (): void {
    $page = $this->rendered('<x-meilifacets::search />');
    $toggle = $this->hooked($page, 'search-toggle')[0];
    $panel = $this->hooked($page, 'search-panel')[0];

    expect($toggle->getAttribute('aria-controls'))->toBe($panel->getAttribute('id'));
    expect($toggle->getAttribute('aria-expanded'))->toBe('false');
    expect($panel->hasAttribute('hidden'))->toBeTrue();
    expect($panel->getAttribute('role'))->toBe('search');
});

it('names the field a combobox', function (): void {
    $input = $this->hooked($this->rendered('<x-meilifacets::search />'), 'search-input')[0];

    expect([
        $input->getAttribute('type'),
        $input->getAttribute('role'),
        $input->getAttribute('aria-autocomplete'),
    ])->toBe(['search', 'combobox', 'list']);
    expect($input->getAttribute('aria-label'))->not->toBe('');
});

/** The field is the box the stylesheet draws the magnifier in and holds at the top of a scrolled panel. */
it('wraps the field in the box that carries its magnifier', function (): void {
    $field = $this->hooked($this->rendered('<x-meilifacets::search />'), 'search-field');

    expect($field)->toHaveCount(1);
    expect($this->hooked($field[0], 'search-input'))->toHaveCount(1);
});

it('hands the theme icon to the toggle of its own composition', function (): void {
    $themed = $this->rendered('<x-meilifacets::search><x-slot:icon><svg class="theme-icon"></svg></x-slot:icon></x-meilifacets::search>');
    $bare = $this->rendered('<x-meilifacets::search name="bare"><x-slot:icon></x-slot:icon></x-meilifacets::search>');
    $default = $this->rendered('<x-meilifacets::search name="default" />');

    expect($this->html($this->hooked($themed, 'search-toggle')[0]))->toContain('class="theme-icon"');
    expect($this->html($this->hooked($bare, 'search-toggle')[0]))->not->toContain('<img');
    expect($this->html($this->hooked($default, 'search-toggle')[0]))->toContain('images/search.svg');
});

it('renders a section with its native heading its limit and a link to its archive', function (): void {
    $section = $this->section($this->rendered('<x-meilifacets::search />'), 'post');
    $seeAll = $this->hooked($section, 'search-see-all');

    expect($section->getAttribute('data-limit'))->toBe((string) SearchSettings::DEFAULT_LIMIT);
    expect($section->hasAttribute('hidden'))->toBeTrue();
    expect($section->textContent)->toContain(get_post_type_object('post')->labels->name);
    expect($seeAll)->toHaveCount(1);
    expect($seeAll[0]->getAttribute('href'))->toBe(get_post_type_archive_link('post'));
    expect(trim($seeAll[0]->textContent))->toBe(get_post_type_object('post')->labels->all_items);
});

/** The link sits in the section's header: read right after the heading, before the options. */
it('places the link to the archive between the heading and the options', function (): void {
    $section = $this->section($this->rendered('<x-meilifacets::search />'), 'post');
    $order = array_map(
        static fn (DOMElement $child): string => $child->getAttribute('data-meili') ?: $child->tagName,
        array_values(array_filter(iterator_to_array($section->childNodes), static fn ($node): bool => $node instanceof DOMElement)),
    );

    expect($order)->toBe(['p', 'search-see-all', 'search-results', 'search-card-template']);
});

it('lists the options of a section in a listbox named by its heading', function (): void {
    $section = $this->section($this->rendered('<x-meilifacets::search />'), 'post');
    $results = $this->hooked($section, 'search-results')[0];
    $heading = $section->getElementsByTagName('p')->item(0);

    expect($results->getAttribute('role'))->toBe('listbox');
    expect($results->getAttribute('aria-labelledby'))->toBe($heading?->getAttribute('id'));
    $this->assertSame(0, new DOMXPath($section->ownerDocument)->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $section)->length, 'a section names its list without adding a heading to the page');
    expect($this->hooked($section, 'search-count'))->toHaveCount(1);
});

it('templates a search card carrying the hooks the client fills', function (): void {
    $template = $this->templateOf($this->rendered('<x-meilifacets::search />'), 'post');

    foreach (['card', 'url', 'image', 'title', 'summary', 'price'] as $hook) {
        $this->assertStringContainsString('data-meili="'.$hook.'"', $template, $hook);
    }

    expect($template)->toContain('role="option"');
});

it('searches only the sections a template places in its order and with its limits', function (): void {
    $page = $this->rendered(<<<'BLADE'
        <x-meilifacets::search>
            <x-meilifacets::search.toggle />
            <x-meilifacets::search.panel>
                <x-meilifacets::search.input />
                <x-meilifacets::search.section type="post" limit="2" />
                <x-meilifacets::search.empty-state />
                <x-meilifacets::search.unavailable />
            </x-meilifacets::search.panel>
        </x-meilifacets::search>
        BLADE);

    expect($this->sectionTypes($page))->toBe(['post']);
    expect($this->section($page, 'post')->getAttribute('data-limit'))->toBe('2');
});

it('renders the bricks where a template places them', function (): void {
    expect($this->acceptedTypes())->toContain('product');

    $page = $this->rendered(<<<'BLADE'
        <x-meilifacets::search>
            <x-meilifacets::search.panel>
                <x-meilifacets::search.empty-state />
                <x-meilifacets::search.section type="post" limit="2" />
                <x-meilifacets::search.section type="product" limit="3" />
                <x-meilifacets::search.input />
                <x-meilifacets::search.unavailable />
            </x-meilifacets::search.panel>
            <x-meilifacets::search.toggle />
        </x-meilifacets::search>
        BLADE);
    $panel = $this->hooked($page, 'search-panel')[0];

    expect($this->childBricks($page, $this->hooked($page, 'search')[0]))->toBe(['search-panel', 'search-toggle']);
    expect($this->childBricks($page, $panel))->toBe(
        ['search-empty', 'search-section:post', 'search-section:product', 'search-field', 'search-unavailable', 'search-status'],
    );
    expect([$this->section($page, 'post')->getAttribute('data-limit'), $this->section($page, 'product')->getAttribute('data-limit')])->toBe(['2', '3']);
    expect($this->hooked($page, 'search-toggle')[0]->getAttribute('aria-controls'))->toBe($panel->getAttribute('id'));
});

it('takes the limit of a section from the settings bound', function (): void {
    $binding = $this->app->getBindings()[SearchSettings::class];
    $this->app->bind(SearchSettings::class, static fn (): SearchSettings => new SearchSettings(limit: 6));

    try {
        $page = $this->rendered('<x-meilifacets::search><x-meilifacets::search.section type="post" /></x-meilifacets::search>');
    } finally {
        $this->app->bind(SearchSettings::class, $binding['concrete'], $binding['shared']);
    }

    expect($this->section($page, 'post')->getAttribute('data-limit'))->toBe('6');
});

it('refuses a section of a type its root does not search', function (): void {
    $this->expectException(ViewException::class);
    $this->expectExceptionMessage('Post type "page" is not searchable');

    $this->rendered('<x-meilifacets::search><x-meilifacets::search.section type="page" /></x-meilifacets::search>');
});

it('refuses two sections of one type in one root', function (): void {
    $this->expectException(ViewException::class);
    $this->expectExceptionMessage('The search "search" already holds a section for "post"');

    $this->rendered(<<<'BLADE'
        <x-meilifacets::search>
            <x-meilifacets::search.section type="post" />
            <x-meilifacets::search.section type="post" />
        </x-meilifacets::search>
        BLADE);
});

it('lets two roots each hold a section of the same type', function (): void {
    $page = $this->rendered(<<<'BLADE'
        <x-meilifacets::search name="header"><x-meilifacets::search.section name="header" type="post" /></x-meilifacets::search>
        <x-meilifacets::search name="footer"><x-meilifacets::search.section name="footer" type="post" /></x-meilifacets::search>
        BLADE);

    expect($this->sectionTypes($page))->toBe(['post', 'post']);
});

it('refuses a limit below one or not whole', function (string $limit, string $message): void {
    $this->expectException(ViewException::class);
    $this->expectExceptionMessage($message);

    $this->rendered("<x-meilifacets::search><x-meilifacets::search.section type=\"post\" {$limit} /></x-meilifacets::search>");
})->with([
    'no result' => ['limit="0"', 'asks for 0 results: it shows a whole number of them, at least 1.'],
    'a negative number' => ['limit="-1"', 'asks for -1 results: it shows a whole number of them, at least 1.'],
    'a bound boolean' => [':limit="true"', 'asks for true results: it shows a whole number of them, at least 1.'],
    'a fraction' => ['limit="2.5"', 'asks for 2.5 results: it shows a whole number of them, at least 1.'],
    'a bound fraction' => [':limit="2.5"', 'asks for 2.5 results: it shows a whole number of them, at least 1.'],
    'a word' => ['limit="abc"', 'asks for abc results: it shows a whole number of them, at least 1.'],
]);

it('takes a whole limit written as text or bound as a number', function (): void {
    $page = $this->rendered(<<<'BLADE'
        <x-meilifacets::search name="text"><x-meilifacets::search.section name="text" type="post" limit="2" /></x-meilifacets::search>
        <x-meilifacets::search name="number"><x-meilifacets::search.section name="number" type="post" :limit="2" /></x-meilifacets::search>
        BLADE);

    expect(array_map(
        static fn (DOMElement $section): string => $section->getAttribute('data-limit'),
        $this->hooked($page, 'search-section'),
    ))->toBe(['2', '2']);
});

it('leaves out the link of a type without archive', function (): void {
    register_post_type(self::NOTE, ['public' => true, 'labels' => ['name' => 'Notes', 'all_items' => 'All notes']]);
    $this->unpinIndexedPostTypes();
    add_filter(self::INDEXED_POST_TYPES_OPTION, static fn (): array => ['post', self::NOTE]);

    $section = $this->section($this->rendered('<x-meilifacets::search />'), self::NOTE);

    expect($this->hooked($section, 'search-see-all'))->toHaveCount(0);
});

it('renders the card a project hands one type', function (): void {
    Blade::component(ProjectSearchCard::class, ProjectSearchCard::ALIAS);
    // `<x-dynamic-component>` keeps the aliases known at its first use in the process.
    (static fn (): null => self::$compiler = null)->bindTo(null, DynamicComponent::class)();
    $this->app->scoped(SearchableTypes::class, fn (): SearchableTypes => new readonly class($this->app->make(WooCommerceSearchableTypes::class)) implements SearchableTypes
    {
        public function __construct(private SearchableTypes $default) {}

        public function all(): array
        {
            $types = $this->default->all();
            $types['post'] = $types['post']->withCard(ProjectSearchCard::ALIAS);

            return $types;
        }
    });

    try {
        $page = $this->rendered('<x-meilifacets::search />');
    } finally {
        $this->app->scoped(SearchableTypes::class, WooCommerceSearchableTypes::class);
    }

    expect($this->templateOf($page, 'post'))->toContain('probeSearchCard');
    expect($this->templateOf($page, 'product'))->not->toContain('probeSearchCard');
});
