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
use Modules\MeiliFacets\Tests\Unit\Doubles\ProbeSearchCard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The bricks of a search root, as the module composes them and as a theme does. */
final class SearchCompositionTest extends TestCase
{
    use PinsIndexedPostTypes;

    private const string NOTE = 'meili_test_note';

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinIndexedPostTypes();
    }

    /** The registry and the declarations are scoped: what one test renders would reach the next. */
    protected function tearDown(): void
    {
        $this->unpinIndexedPostTypes();
        unregister_post_type(self::NOTE);
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function it_composes_every_brick_itself_when_the_template_hands_it_nothing(): void
    {
        $page = $this->rendered('<x-meilifacets::search />');

        foreach (['search-toggle', 'search-panel', 'search-input', 'search-empty', 'search-unavailable', 'search-status'] as $hook) {
            $this->assertCount(1, $this->hooked($page, $hook), $hook);
        }

        $this->assertSame($this->acceptedTypes(), $this->sectionTypes($page));
    }

    #[Test]
    public function it_opens_the_panel_it_renders_and_keeps_both_closed(): void
    {
        $page = $this->rendered('<x-meilifacets::search />');
        $toggle = $this->hooked($page, 'search-toggle')[0];
        $panel = $this->hooked($page, 'search-panel')[0];

        $this->assertSame($panel->getAttribute('id'), $toggle->getAttribute('aria-controls'));
        $this->assertSame('false', $toggle->getAttribute('aria-expanded'));
        $this->assertTrue($panel->hasAttribute('hidden'));
        $this->assertSame('search', $panel->getAttribute('role'));
    }

    #[Test]
    public function it_names_the_field_a_combobox(): void
    {
        $input = $this->hooked($this->rendered('<x-meilifacets::search />'), 'search-input')[0];

        $this->assertSame(['search', 'combobox', 'list'], [
            $input->getAttribute('type'),
            $input->getAttribute('role'),
            $input->getAttribute('aria-autocomplete'),
        ]);
        $this->assertNotSame('', $input->getAttribute('aria-label'));
    }

    /** The field is the box the stylesheet draws the magnifier in and holds at the top of a scrolled panel. */
    #[Test]
    public function it_wraps_the_field_in_the_box_that_carries_its_magnifier(): void
    {
        $field = $this->hooked($this->rendered('<x-meilifacets::search />'), 'search-field');

        $this->assertCount(1, $field);
        $this->assertCount(1, $this->hooked($field[0], 'search-input'));
    }

    #[Test]
    public function it_hands_the_theme_icon_to_the_toggle_of_its_own_composition(): void
    {
        $themed = $this->rendered('<x-meilifacets::search><x-slot:icon><svg class="theme-icon"></svg></x-slot:icon></x-meilifacets::search>');
        $bare = $this->rendered('<x-meilifacets::search name="bare"><x-slot:icon></x-slot:icon></x-meilifacets::search>');
        $default = $this->rendered('<x-meilifacets::search name="default" />');

        $this->assertStringContainsString('class="theme-icon"', $this->html($this->hooked($themed, 'search-toggle')[0]));
        $this->assertStringNotContainsString('<img', $this->html($this->hooked($bare, 'search-toggle')[0]));
        $this->assertStringContainsString('images/search.svg', $this->html($this->hooked($default, 'search-toggle')[0]));
    }

    #[Test]
    public function it_renders_a_section_with_its_native_heading_its_limit_and_a_link_to_its_archive(): void
    {
        $section = $this->section($this->rendered('<x-meilifacets::search />'), 'post');
        $seeAll = $this->hooked($section, 'search-see-all');

        $this->assertSame((string) SearchSettings::DEFAULT_LIMIT, $section->getAttribute('data-limit'));
        $this->assertTrue($section->hasAttribute('hidden'));
        $this->assertStringContainsString(get_post_type_object('post')->labels->name, $section->textContent);
        $this->assertCount(1, $seeAll);
        $this->assertSame(get_post_type_archive_link('post'), $seeAll[0]->getAttribute('href'));
        $this->assertSame(get_post_type_object('post')->labels->all_items, trim($seeAll[0]->textContent));
    }

    /** The link sits in the section's header: read right after the heading, before the options. */
    #[Test]
    public function it_places_the_link_to_the_archive_between_the_heading_and_the_options(): void
    {
        $section = $this->section($this->rendered('<x-meilifacets::search />'), 'post');
        $order = array_map(
            static fn (DOMElement $child): string => $child->getAttribute('data-meili') ?: $child->tagName,
            array_values(array_filter(iterator_to_array($section->childNodes), static fn ($node): bool => $node instanceof DOMElement)),
        );

        $this->assertSame(['h2', 'search-see-all', 'search-results', 'search-card-template'], $order);
    }

    #[Test]
    public function it_lists_the_options_of_a_section_in_a_listbox_named_by_its_heading(): void
    {
        $section = $this->section($this->rendered('<x-meilifacets::search />'), 'post');
        $results = $this->hooked($section, 'search-results')[0];
        $heading = $section->getElementsByTagName('h2')->item(0);

        $this->assertSame('listbox', $results->getAttribute('role'));
        $this->assertSame($heading?->getAttribute('id'), $results->getAttribute('aria-labelledby'));
        $this->assertCount(1, $this->hooked($section, 'search-count'));
    }

    #[Test]
    public function it_templates_a_search_card_carrying_the_hooks_the_client_fills(): void
    {
        $template = $this->templateOf($this->rendered('<x-meilifacets::search />'), 'post');

        foreach (['card', 'url', 'image', 'title', 'summary', 'price'] as $hook) {
            $this->assertStringContainsString('data-meili="'.$hook.'"', $template, $hook);
        }

        $this->assertStringContainsString('role="option"', $template);
    }

    #[Test]
    public function it_searches_only_the_sections_a_template_places_in_its_order_and_with_its_limits(): void
    {
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

        $this->assertSame(['post'], $this->sectionTypes($page));
        $this->assertSame('2', $this->section($page, 'post')->getAttribute('data-limit'));
    }

    #[Test]
    public function it_takes_the_limit_of_a_section_from_the_settings_bound(): void
    {
        $binding = $this->app->getBindings()[SearchSettings::class];
        $this->app->bind(SearchSettings::class, static fn (): SearchSettings => new SearchSettings(limit: 6));

        try {
            $page = $this->rendered('<x-meilifacets::search><x-meilifacets::search.section type="post" /></x-meilifacets::search>');
        } finally {
            $this->app->bind(SearchSettings::class, $binding['concrete'], $binding['shared']);
        }

        $this->assertSame('6', $this->section($page, 'post')->getAttribute('data-limit'));
    }

    #[Test]
    public function it_refuses_a_section_of_a_type_its_root_does_not_search(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Post type "page" is not searchable');

        $this->rendered('<x-meilifacets::search><x-meilifacets::search.section type="page" /></x-meilifacets::search>');
    }

    #[Test]
    public function it_refuses_two_sections_of_one_type_in_one_root(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('The search "search" already holds a section for "post"');

        $this->rendered(<<<'BLADE'
            <x-meilifacets::search>
                <x-meilifacets::search.section type="post" />
                <x-meilifacets::search.section type="post" />
            </x-meilifacets::search>
            BLADE);
    }

    #[Test]
    public function it_lets_two_roots_each_hold_a_section_of_the_same_type(): void
    {
        $page = $this->rendered(<<<'BLADE'
            <x-meilifacets::search name="header"><x-meilifacets::search.section name="header" type="post" /></x-meilifacets::search>
            <x-meilifacets::search name="footer"><x-meilifacets::search.section name="footer" type="post" /></x-meilifacets::search>
            BLADE);

        $this->assertSame(['post', 'post'], $this->sectionTypes($page));
    }

    #[Test]
    public function it_refuses_a_section_asking_for_no_result(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('asks for 0 results');

        $this->rendered('<x-meilifacets::search><x-meilifacets::search.section type="post" limit="0" /></x-meilifacets::search>');
    }

    #[Test]
    public function it_leaves_out_the_link_of_a_type_without_archive(): void
    {
        register_post_type(self::NOTE, ['public' => true, 'labels' => ['name' => 'Notes', 'all_items' => 'All notes']]);
        $this->unpinIndexedPostTypes();
        add_filter(self::INDEXED_POST_TYPES_OPTION, static fn (): array => ['post', self::NOTE]);

        $section = $this->section($this->rendered('<x-meilifacets::search />'), self::NOTE);

        $this->assertCount(0, $this->hooked($section, 'search-see-all'));
    }

    #[Test]
    public function it_renders_the_card_a_project_hands_one_type(): void
    {
        Blade::component(ProbeSearchCard::class, ProbeSearchCard::ALIAS);
        // `<x-dynamic-component>` keeps the aliases known at its first use in the process.
        (static fn (): null => self::$compiler = null)->bindTo(null, DynamicComponent::class)();
        $this->app->scoped(SearchableTypes::class, fn (): SearchableTypes => new readonly class($this->app->make(WooCommerceSearchableTypes::class)) implements SearchableTypes
        {
            public function __construct(private SearchableTypes $default) {}

            public function all(): array
            {
                $types = $this->default->all();
                $types['post'] = $types['post']->withCard(ProbeSearchCard::ALIAS);

                return $types;
            }
        });

        try {
            $page = $this->rendered('<x-meilifacets::search />');
        } finally {
            $this->app->scoped(SearchableTypes::class, WooCommerceSearchableTypes::class);
        }

        $this->assertStringContainsString('probeSearchCard', $this->templateOf($page, 'post'));
        $this->assertStringNotContainsString('probeSearchCard', $this->templateOf($page, 'product'));
    }

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
