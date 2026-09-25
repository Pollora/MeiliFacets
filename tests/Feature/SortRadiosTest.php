<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Listing\CurrentListing;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Step 4b: the sort drawn as radios, in a section like a facet's (C-4). */
final class SortRadiosTest extends TestCase
{
    use SwitchesTheSiteLocale;

    private const string COLLAPSIBLE = '<x-meilifacets::sort widget="radios" collapsible />';

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
    public function it_draws_one_radio_per_sort_with_the_one_in_force_checked(): void
    {
        $sorts = $this->app->make(CurrentListing::class)->sole()->sorts();
        $choices = $this->rendered('<x-meilifacets::sort widget="radios" />')->querySelectorAll($this->hooked(Hook::SortChoice));

        $this->assertCount(count($sorts) + 1, $choices);
        $this->assertSame('', $choices[0]->getAttribute('value'));
        $this->assertTrue($choices[0]->hasAttribute('checked'));
        $this->assertSame(['radio'], array_values(array_unique(array_map(static fn ($choice): string => $choice->getAttribute('type'), iterator_to_array($choices)))));
    }

    #[Test]
    public function it_names_the_group_and_closes_it_behind_a_trigger_when_collapsible(): void
    {
        $document = $this->rendered('<x-meilifacets::sort widget="radios" collapsible />');
        $group = $document->querySelector($this->hooked(Hook::SortChoices));
        $toggle = $group->querySelector('legend > '.$this->hooked(Hook::Toggle));
        $panel = $document->getElementById($toggle->getAttribute('aria-controls'));

        $this->assertSame('fieldset', strtolower($group->localName));
        $this->assertSame(__('Sort by'), trim($toggle->querySelector('.meilifacetsFacetToggleLabel')->textContent));
        $this->assertSame('false', $toggle->getAttribute('aria-expanded'));
        $this->assertTrue($panel->matches($this->hooked(Hook::Panel)));
        $this->assertTrue($panel->hasAttribute('hidden'));
    }

    /** The trigger names the order in force, in its text: that text is its accessible name, label first. */
    #[Test]
    public function it_names_the_default_order_in_its_trigger(): void
    {
        $toggle = $this->underLocales('en', 'en_US', fn (): HTMLDocument => $this->rendered(self::COLLAPSIBLE))->querySelector($this->hooked(Hook::Toggle));

        $this->assertSame('Relevance', $toggle->querySelector($this->hooked(Hook::SortChosen))->textContent);
        $this->assertSame('Sort by: Relevance', $this->nameOf($toggle));
        $this->assertSame('Sort by', $toggle->querySelector('.meilifacetsFacetToggleLabel')->textContent);
    }

    #[Test]
    public function it_names_the_order_the_url_asks_for(): void
    {
        $value = array_key_first($this->app->make(CurrentListing::class)->sole()->sorts());
        request()->query->replace(['sort' => $value]);
        $this->app->forgetScopedInstances();

        [$label, $toggle] = $this->underLocales('en', 'en_US', fn (): array => [
            $this->app->make(CurrentListing::class)->sole()->sorts()[$value]->label,
            $this->rendered(self::COLLAPSIBLE)->querySelector($this->hooked(Hook::Toggle)),
        ]);

        $this->assertSame('Sort by: '.$label, $this->nameOf($toggle));
    }

    /** French sets a no-break space before the colon, so the value never wraps away from its label. */
    #[Test]
    public function it_writes_the_french_colon_after_a_no_break_space(): void
    {
        $toggle = $this->underLocales('fr', 'fr_FR', fn (): HTMLDocument => $this->rendered(self::COLLAPSIBLE))->querySelector($this->hooked(Hook::Toggle));

        $this->assertSame("Trier par\u{00A0}: Pertinence", $this->nameOf($toggle));
    }

    /** A sort is an order: its trigger never shows a count. */
    #[Test]
    public function it_keeps_the_badge_of_its_trigger_empty(): void
    {
        request()->query->replace(['sort' => array_key_first($this->app->make(CurrentListing::class)->sole()->sorts())]);
        $this->app->forgetScopedInstances();

        $badge = $this->rendered('<x-meilifacets::sort widget="radios" collapsible />')->querySelector($this->hooked(Hook::SelectedCount));

        $this->assertTrue($badge->hasAttribute('hidden'));
        $this->assertSame('', $badge->textContent);
    }

    #[Test]
    public function it_refuses_a_second_sort_whatever_its_widget(): void
    {
        $this->expectExceptionMessageMatches('/The sort of listing "[^"]+" is rendered twice/');

        Blade::render('<x-meilifacets::sort /><x-meilifacets::sort widget="radios" />');
    }

    /** The listbox stays the default, and the column renders as it did. */
    #[Test]
    public function it_draws_the_listbox_unless_asked(): void
    {
        $default = Blade::render('<x-meilifacets::sort />');
        $this->app->forgetScopedInstances();

        $this->assertSame(Blade::render('<x-meilifacets::sort widget="listbox" />'), $default);
        $this->assertStringContainsString(Hook::SortTrigger->attribute()->toHtml(), $default);
        $this->assertStringNotContainsString(Hook::SortChoices->attribute()->toHtml(), $default);
    }

    private function rendered(string $blade): HTMLDocument
    {
        return HTMLDocument::createFromString('<div>'.Blade::render($blade).'</div>', LIBXML_NOERROR);
    }

    /** The badge is `aria-hidden`, and empty for a sort: the rest of the text is the name. */
    private function nameOf(Element $toggle): string
    {
        return trim($toggle->textContent);
    }

    private function hooked(Hook $hook): string
    {
        return '['.Contract::Attribute->value.'="'.$hook->value.'"]';
    }
}
