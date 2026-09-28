<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\SiteSearch\AcceptedSearchTypes;
use Modules\MeiliFacets\SiteSearch\SearchRegistry;
use Modules\MeiliFacets\SiteSearch\SearchRoot;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SearchComponentTest extends TestCase
{
    use PinsIndexedPostTypes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinIndexedPostTypes();
    }

    /** The registry is scoped: roots rendered here would reach the next test of the shared application. */
    protected function tearDown(): void
    {
        $this->unpinIndexedPostTypes();
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function it_renders_a_container_the_client_can_find_by_name(): void
    {
        $html = Blade::render('<x-meilifacets::search class="header-search">slot</x-meilifacets::search>');

        $this->assertStringContainsString('data-meili="search"', $html);
        $this->assertStringContainsString('data-search="'.SearchRoot::DEFAULT_NAME.'"', $html);
        $this->assertStringContainsString((string) Contract::version(), $html);
        $this->assertStringContainsString('class="header-search"', $html);
        $this->assertStringContainsString('slot', $html);
    }

    #[Test]
    public function it_takes_its_settings_from_the_container(): void
    {
        Blade::render('<x-meilifacets::search />');

        $this->assertEquals(new SearchSettings, $this->root(SearchRoot::DEFAULT_NAME)->settings);
    }

    #[Test]
    public function it_lets_a_template_override_the_threshold_and_the_delay(): void
    {
        Blade::render('<x-meilifacets::search min-chars="3" delay="250" />');

        $this->assertEquals(new SearchSettings(3, 250), $this->root(SearchRoot::DEFAULT_NAME)->settings);
    }

    #[Test]
    public function it_lets_a_project_change_the_defaults_everywhere(): void
    {
        $binding = $this->app->getBindings()[SearchSettings::class];
        $this->app->bind(SearchSettings::class, static fn (): SearchSettings => new SearchSettings(3, 300, 8));

        try {
            Blade::render('<x-meilifacets::search delay="50" />');

            $this->assertEquals(new SearchSettings(3, 50, 8), $this->root(SearchRoot::DEFAULT_NAME)->settings);
        } finally {
            $this->app->bind(SearchSettings::class, $binding['concrete'], $binding['shared']);
        }
    }

    #[Test]
    public function it_lets_two_roots_of_different_names_share_a_page(): void
    {
        $html = Blade::render('<x-meilifacets::search name="header" /><x-meilifacets::search name="footer" min-chars="4" />');

        $this->assertStringContainsString('data-search="header"', $html);
        $this->assertStringContainsString('data-search="footer"', $html);
        $this->assertSame(2, $this->root('header')->settings->minChars);
        $this->assertSame(4, $this->root('footer')->settings->minChars);
    }

    #[Test]
    public function it_refuses_two_roots_under_one_name(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('A search named "search" is already rendered');

        Blade::render('<x-meilifacets::search /><x-meilifacets::search />');
    }

    /** What the automatic sections of step 5 will iterate over. */
    #[Test]
    public function it_holds_the_accepted_types_in_their_declared_order(): void
    {
        Blade::render('<x-meilifacets::search />');

        $this->assertSame(
            array_keys($this->app->make(AcceptedSearchTypes::class)->all()),
            array_keys($this->root(SearchRoot::DEFAULT_NAME)->types)
        );
        $this->assertNotSame([], $this->root(SearchRoot::DEFAULT_NAME)->types);
    }

    private function root(string $name): SearchRoot
    {
        return $this->app->make(SearchRegistry::class)->named($name);
    }
}
