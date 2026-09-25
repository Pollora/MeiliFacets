<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Hook;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Step 5b: « Apply (X) » on its own, and « Clear all » drawn as an icon, for the footer of the drawer. */
final class DrawerFooterTest extends TestCase
{
    use FindsHooks;
    use HoldsCatalogueValues;

    private mixed $applyMode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applyMode = config('meilifacets.apply_mode');
        $this->app->forgetScopedInstances();
    }

    /** The suite shares one application: a mode or an address left here would reach a later class. */
    protected function tearDown(): void
    {
        config(['meilifacets.apply_mode' => $this->applyMode]);
        request()->query->replace([]);
        $this->app->forgetScopedInstances();

        parent::tearDown();
    }

    #[Test]
    public function it_renders_apply_with_the_count_it_would_apply_when_the_listing_waits_for_it(): void
    {
        config(['meilifacets.apply_mode' => 'submit']);
        $this->holdTwoValues();

        $apply = $this->rendered('<x-meilifacets::apply />')->querySelector($this->hooked(Hook::Apply));
        $count = $apply->querySelector($this->hooked(Hook::ActiveCount));

        $this->assertSame('button', $apply->getAttribute('type'));
        $this->assertSame(__('Apply'), trim($apply->firstChild->textContent));
        $this->assertSame('2', $count->textContent);
        $this->assertSame($count->getAttribute('id'), $apply->getAttribute('aria-describedby'));
        $this->assertSame('true', $count->getAttribute('aria-hidden'));
    }

    /** C-1: in `immediate` the button is a landmark of the drawer, rendered only when asked. */
    #[Test]
    public function it_renders_apply_in_immediate_only_when_visible_in_drawer(): void
    {
        config(['meilifacets.apply_mode' => 'immediate']);

        $this->assertNull($this->rendered('<x-meilifacets::apply />')->querySelector($this->hooked(Hook::Apply)));
        $this->assertNotNull($this->rendered('<x-meilifacets::apply visible-in-drawer />')->querySelector($this->hooked(Hook::Apply)));
    }

    /** In `immediate` every box searches already: the button only marks the way out of the sheet, and the sheet alone shows it. */
    #[Test]
    public function it_marks_apply_for_the_sheet_only_when_the_listing_searches_at_once(): void
    {
        config(['meilifacets.apply_mode' => 'immediate']);
        $immediate = $this->rendered('<x-meilifacets::apply visible-in-drawer />')->querySelector($this->hooked(Hook::Apply));
        $this->app->forgetScopedInstances();
        config(['meilifacets.apply_mode' => 'submit']);
        $submit = $this->rendered('<x-meilifacets::apply visible-in-drawer />')->querySelector($this->hooked(Hook::Apply));

        $this->assertSame('sheet', $immediate->getAttribute('data-only'));
        $this->assertFalse($submit->hasAttribute('data-only'));
    }

    #[Test]
    public function it_hides_and_empties_the_count_while_nothing_is_held(): void
    {
        config(['meilifacets.apply_mode' => 'submit']);

        $count = $this->rendered('<x-meilifacets::apply />')->querySelector($this->hooked(Hook::ActiveCount));

        $this->assertTrue($count->hasAttribute('hidden'));
        $this->assertSame('', $count->textContent);
    }

    #[Test]
    public function the_group_hands_its_button_over_when_asked(): void
    {
        config(['meilifacets.apply_mode' => 'submit']);

        $this->assertStringNotContainsString(Hook::Apply->attribute()->toHtml(), Blade::render('<x-meilifacets::facets :with-apply="false" />'));
    }

    /** What the column renders today must not move by a byte. */
    #[Test]
    public function the_group_renders_its_button_as_before_unless_asked(): void
    {
        config(['meilifacets.apply_mode' => 'submit']);
        $explicit = Blade::render('<x-meilifacets::facets :with-apply="true" />');
        $this->app->forgetScopedInstances();

        $this->assertSame($explicit, Blade::render('<x-meilifacets::facets />'));
        $this->assertStringContainsString(Hook::Apply->attribute()->toHtml(), $explicit);
    }

    #[Test]
    public function it_draws_clear_all_as_an_icon_named_by_its_words(): void
    {
        $this->holdTwoValues();

        $reset = $this->rendered('<x-meilifacets::reset shape="icon" />')->querySelector($this->hooked(Hook::Reset));
        $image = $reset->querySelector('img');

        $this->assertSame(__('Clear all'), $reset->getAttribute('aria-label'));
        $this->assertSame('icon', $reset->getAttribute('data-shape'));
        $this->assertSame('', trim($reset->textContent));
        $this->assertStringEndsWith('/modules/meilifacets/images/trash.svg', $image->getAttribute('src'));
        $this->assertSame('', $image->getAttribute('alt'));
        $this->assertSame(['20', '20', 'lazy', 'low'], [
            $image->getAttribute('width'), $image->getAttribute('height'), $image->getAttribute('loading'), $image->getAttribute('fetchpriority'),
        ]);
    }

    #[Test]
    public function it_takes_the_icon_a_theme_gives_and_none_when_the_slot_is_empty(): void
    {
        $given = $this->rendered('<x-meilifacets::reset shape="icon"><x-slot:icon><svg id="bin"></svg></x-slot:icon></x-meilifacets::reset>')
            ->querySelector($this->hooked(Hook::Reset));
        $this->app->forgetScopedInstances();
        $emptied = $this->rendered('<x-meilifacets::reset shape="icon"><x-slot:icon></x-slot:icon></x-meilifacets::reset>')
            ->querySelector($this->hooked(Hook::Reset));

        $this->assertNotNull($given->querySelector('svg#bin'));
        $this->assertNull($given->querySelector('img'));
        $this->assertSame('true', $given->firstElementChild->getAttribute('aria-hidden'));
        $this->assertSame(0, $emptied->childElementCount);
        $this->assertSame(__('Clear all'), $emptied->getAttribute('aria-label'));
    }

    /** The text variant is the default, and it renders as it always has. */
    #[Test]
    public function it_renders_the_text_reset_unchanged_without_a_shape(): void
    {
        $reset = $this->rendered('<x-meilifacets::reset />')->querySelector($this->hooked(Hook::Reset));

        $this->assertSame(__('Clear all'), trim($reset->textContent));
        $this->assertFalse($reset->hasAttribute('aria-label'));
        $this->assertFalse($reset->hasAttribute('data-shape'));
    }

    /** A variant beside the default, never in its place. */
    #[Test]
    public function it_marks_the_pill_reset_and_only_it(): void
    {
        $pill = $this->rendered('<x-meilifacets::reset shape="pill" />')->querySelector($this->hooked(Hook::Reset));
        $text = $this->rendered('<x-meilifacets::reset shape="text" />')->querySelector($this->hooked(Hook::Reset));

        $this->assertSame('pill', $pill->getAttribute('data-shape'));
        $this->assertSame(__('Clear all'), trim($pill->textContent));
        $this->assertFalse($text->hasAttribute('data-shape'));
    }

    #[Test]
    public function it_points_at_a_trash_icon_that_was_published(): void
    {
        $published = public_path('modules/meilifacets/images/trash.svg');

        $this->assertFileExists($published);
        $this->assertFileEquals(module_path('MeiliFacets', 'resources/assets/images/trash.svg'), $published);
    }

    private function rendered(string $blade): HTMLDocument
    {
        return HTMLDocument::createFromString('<div>'.Blade::render($blade).'</div>', LIBXML_NOERROR);
    }
}
