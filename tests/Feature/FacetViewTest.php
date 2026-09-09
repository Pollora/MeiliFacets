<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\View\ElementId;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FacetViewTest extends TestCase
{
    /** What a page placed lives as long as its listing, and the suite shares one application. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->forgetScopedInstances();
    }

    #[Test]
    public function it_hangs_the_hook_on_the_outermost_element(): void
    {
        $opening = explode('>', trim($this->render()))[0];

        $this->assertStringStartsWith('<fieldset', $opening);
        $this->assertStringContainsString(Hook::Facet->attribute()->toHtml(), $opening);
    }

    /** A grid row collapses only if something inside it can hide its overflow. */
    #[Test]
    public function it_wraps_the_values_in_a_panel_a_theme_can_collapse(): void
    {
        $this->assertMatchesRegularExpression(
            '/<div class="meilifacetsFacetPanel"[^>]*>\s*<div class="meilifacetsFacetPanelInner">/',
            $this->render()
        );
    }

    /** Without an id, a theme's disclosure has nothing to point `aria-controls` at. */
    #[Test]
    public function it_names_the_panel(): void
    {
        $this->assertStringContainsString(
            'id="'.new ElementId('products')->facetPanel('product_brand').'"',
            $this->render()
        );
    }

    /** The fold button belongs to the panel: collapsing the facet must take it along. */
    #[Test]
    public function it_keeps_the_fold_button_inside_the_panel(): void
    {
        $panel = $this->render();
        $inner = strpos($panel, 'meilifacetsFacetPanelInner');

        $this->assertGreaterThan($inner, strpos($panel, Hook::More->attribute()->toHtml()));
    }

    private function render(): string
    {
        return Blade::render('<x-meilifacets::facet facet="brand" />');
    }
}
