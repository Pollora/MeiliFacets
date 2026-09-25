<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Listing\CurrentListing;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Step 4b: the sort drawn as radios, in a section like a facet's (C-4). */
final class SortRadiosTest extends TestCase
{
    use FindsHooks;
    use SwitchesTheSiteLocale;

    private const string COLLAPSIBLE = '<x-meilifacets::sort widget="radios" collapsible />';

    /** A locale no catalogue ships, whose sentence puts the order first. */
    private const string VALUE_FIRST = 'xx';

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

    /** The client hides a row and names the order in force through these, never through a tag. */
    #[Test]
    public function it_hooks_each_choice_row_and_publishes_each_label(): void
    {
        $sorts = $this->app->make(CurrentListing::class)->sole()->sorts();
        $rows = $this->rendered('<x-meilifacets::sort widget="radios" />')->querySelectorAll($this->hooked(Hook::SortChoiceRow));

        $this->assertCount(count($sorts) + 1, $rows);

        foreach ($rows as $row) {
            $choice = $row->querySelector($this->hooked(Hook::SortChoice));

            $this->assertSame(trim($row->textContent), $choice->getAttribute('data-label'));
        }
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

    /** A language may put the order first: the sentence is cut where it puts it, never where English does. */
    #[Test]
    public function it_writes_the_order_where_the_sentence_puts_it(): void
    {
        app('translator')->addLines(['*.Sort by: :choice' => ':choice — sort'], self::VALUE_FIRST);

        $toggle = $this->underLocales(self::VALUE_FIRST, self::VALUE_FIRST, fn (): HTMLDocument => $this->rendered(self::COLLAPSIBLE))->querySelector($this->hooked(Hook::Toggle));
        $chosen = $toggle->querySelector($this->hooked(Hook::SortChosen));

        $this->assertSame('', $chosen->previousSibling?->textContent ?? '');
        $this->assertSame(' — sort', $chosen->nextSibling?->textContent);
        $this->assertSame('Relevance — sort', $this->nameOf($toggle));
    }

    /** The label stands alone in the section: the sentence says it again, so it is never read out twice. */
    #[Test]
    public function it_hides_the_label_the_sentence_restates(): void
    {
        $toggle = $this->rendered(self::COLLAPSIBLE)->querySelector($this->hooked(Hook::Toggle));

        $this->assertSame('true', $toggle->querySelector('.meilifacetsFacetToggleLabel')->getAttribute('aria-hidden'));
    }

    /** A sort is an order: its trigger has no count, and nothing it could be described by. */
    #[Test]
    public function it_gives_its_trigger_no_badge(): void
    {
        request()->query->replace(['sort' => array_key_first($this->app->make(CurrentListing::class)->sole()->sorts())]);
        $this->app->forgetScopedInstances();

        $document = $this->rendered('<x-meilifacets::sort widget="radios" collapsible />');

        $this->assertNull($document->querySelector($this->hooked(Hook::SelectedCount)));
        $this->assertFalse($document->querySelector($this->hooked(Hook::Toggle))->hasAttribute('aria-describedby'));
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
