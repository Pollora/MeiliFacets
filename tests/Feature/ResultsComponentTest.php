<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\Listing\Pagination;
use Modules\MeiliFacets\Seo\ItemList;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** `$items` is null on a page robots are told to skip — the state the view must not assume. */
final class ResultsComponentTest extends TestCase
{
    #[Test]
    public function it_announces_the_outage_instead_of_an_empty_list(): void
    {
        $html = $this->render(failed: true);

        $this->assertStringContainsString('meilifacetsUnavailable', $html);
        $this->assertStringNotContainsString('<ul', $html);
    }

    /** Kept for the client to fill, hidden so no screen reader counts an empty list. */
    #[Test]
    public function it_hides_the_list_it_has_nothing_to_put_in(): void
    {
        $html = $this->render(cards: []);

        $this->assertStringContainsString('<ul class="meilifacetsResults" hidden', $html);
        $this->assertStringNotContainsString('class="meilifacetsResultsEmpty" hidden', $html);
    }

    #[Test]
    public function it_lists_the_cards_it_was_given(): void
    {
        $html = $this->render(cards: [$this->card('First'), $this->card('Second')]);

        $this->assertStringContainsString('First', $html);
        $this->assertStringContainsString('Second', $html);
        $this->assertStringContainsString('class="meilifacetsResultsEmpty" hidden', $html);
    }

    /** There are results, they are simply not on the page that was asked for. */
    #[Test]
    public function it_says_which_of_the_two_emptinesses_it_is(): void
    {
        // Whatever language the site runs in, the two must not read the same.
        $nothingAtAll = $this->messageOf($this->render(cards: []));
        $nothingHere = $this->messageOf($this->render(cards: [], pastTheEnd: true));

        $this->assertNotSame('', $nothingAtAll);
        $this->assertNotSame($nothingAtAll, $nothingHere);
    }

    private function messageOf(string $html): string
    {
        preg_match('/meilifacetsResultsEmpty"[^>]*>([^<]*)/', $html, $found);

        return trim($found[1] ?? '');
    }

    /** One clone source, so the client never carries markup of its own. */
    #[Test]
    public function it_offers_a_card_template_the_client_can_clone(): void
    {
        $html = $this->render(cards: [$this->card('First')]);

        $this->assertStringContainsString('<template data-meili="card-template">', $html);
        $this->assertSame(2, substr_count($html, 'data-meili="card"'));
    }

    /** No template on an outage: the contract fails and the client stays out. */
    #[Test]
    public function it_offers_nothing_to_clone_when_the_search_is_down(): void
    {
        $this->assertStringNotContainsString('card-template', $this->render(failed: true));
    }

    #[Test]
    public function it_publishes_the_structured_list(): void
    {
        $html = $this->render(cards: [$this->card('First')], items: new ItemList([$this->card('First')]));

        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"ItemList"', $html);
    }

    #[Test]
    public function it_survives_a_page_that_publishes_no_structured_list(): void
    {
        $html = $this->render(cards: [$this->card('First')], items: null);

        $this->assertStringContainsString('meilifacetsResultsItem', $html);
        $this->assertStringNotContainsString('application/ld+json', $html);
    }

    #[Test]
    public function it_writes_no_empty_structured_list(): void
    {
        $html = $this->render(cards: [$this->card('First')], items: new ItemList([]));

        $this->assertStringNotContainsString('application/ld+json', $html);
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
    private function render(bool $failed = false, array $cards = [], ?ItemList $items = null, bool $pastTheEnd = false): string
    {
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
        return (string) preg_replace('/\s+/', ' ', view('meilifacets::components.results', [
            'listing' => $resolved,
            'cards' => $cards,
            'items' => $items,
            'hook' => fn (string $name) => Hook::from($name)->attribute(),
            'priority' => fn (): ImagePriority => ImagePriority::Lazy,
        ])->render());
    }
}
