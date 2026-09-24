<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\View\CountLabel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The count the server found, in words, where a screen reader hears it change. */
final class TotalComponentTest extends TestCase
{
    use SwitchesTheSiteLocale;

    private const string NOTHING_MATCHES = 'qqxxzzww-aucun-produit-ne-correspond';

    /** The suite shares one application: a listing resolved here would reach a later class. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetScopedInstances();
    }

    protected function tearDown(): void
    {
        request()->query->replace([]);
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function it_counts_what_the_listing_found(): void
    {
        $total = $this->app->make(CurrentListing::class)->sole()->total();

        $this->assertGreaterThan(1, $total);
        $this->assertStringContainsString('>'.$total.' items<', $this->underLocales('en', 'en_US', $this->renderComponent(...)));
    }

    /** Rendered at zero too: the client rewrites the region, it never creates one. */
    #[Test]
    public function it_speaks_the_language_wordpress_translates_in_even_when_nothing_matches(): void
    {
        request()->query->replace([$this->app->make(UrlParameters::class)->reserved(QueryParameter::Query) => self::NOTHING_MATCHES]);

        $this->assertStringContainsString('>0 items<', $this->underLocales('fr', 'en_US', $this->renderComponent(...)));
        $this->assertStringContainsString('>0 article<', $this->underLocales('fr', 'fr_FR', $this->renderComponent(...)));
    }

    #[Test]
    public function it_agrees_with_what_it_counts(): void
    {
        $this->assertStringContainsString('>1 article<', $this->underLocales('fr', 'fr_FR', fn (): string => $this->renderView(1)));
        $this->assertStringContainsString('>88 articles<', $this->underLocales('fr', 'fr_FR', fn (): string => $this->renderView(88)));
    }

    /** Announced when it changes, and whole: « 88 », read alone, names nothing. */
    #[Test]
    public function it_announces_itself_politely_and_whole(): void
    {
        $opening = explode('>', $this->renderView(3))[0];

        $this->assertStringContainsString('aria-live="polite"', $opening);
        $this->assertStringContainsString('aria-atomic="true"', $opening);
        $this->assertStringContainsString(Hook::Total->attribute()->toHtml(), $opening);
    }

    #[Test]
    public function it_keeps_the_classes_a_theme_adds(): void
    {
        $this->assertStringContainsString(
            'class="meilifacetsTotal ml-auto"',
            Blade::render('<x-meilifacets::total class="ml-auto" />'),
        );
    }

    /** A translation catalogue is written by hand, and a theme can override it. */
    #[Test]
    public function it_escapes_its_label(): void
    {
        $html = (string) view('meilifacets::components.total', [
            'label' => '<script>alert(1)</script>',
            'attributes' => new ComponentAttributeBag,
            'hook' => fn (string $name): HtmlString => Hook::from($name)->attribute(),
        ])->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    private function renderComponent(): string
    {
        return Blade::render('<x-meilifacets::total />');
    }

    private function renderView(int $count): string
    {
        return (string) view('meilifacets::components.total', [
            'label' => $this->app->make(CountLabel::class)->of(__(':count item|:count items'), $count),
            'attributes' => new ComponentAttributeBag,
            'hook' => fn (string $name): HtmlString => Hook::from($name)->attribute(),
        ])->render();
    }
}
