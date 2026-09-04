<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Seo\ItemList;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The three states the view must keep apart, and the one it must not assume:
 * itemList() is null on a page robots are told to skip.
 */
final class ResultsViewTest extends TestCase
{
    #[Test]
    public function it_announces_the_outage_instead_of_an_empty_list(): void
    {
        $html = $this->render(failed: true);

        $this->assertStringContainsString('meilifacetsUnavailable', $html);
        $this->assertStringNotContainsString('<ul', $html);
    }

    #[Test]
    public function it_leaves_no_empty_list_for_a_screen_reader_to_count(): void
    {
        $html = $this->render(cards: []);

        $this->assertStringNotContainsString('<ul', $html);
        $this->assertStringContainsString('meilifacetsResultsEmpty', $html);
    }

    #[Test]
    public function it_lists_the_cards_it_was_given(): void
    {
        $html = $this->render(cards: [$this->card('First'), $this->card('Second')]);

        $this->assertSame(2, substr_count($html, 'meilifacetsResultsItem'));
        $this->assertStringNotContainsString('meilifacetsResultsEmpty', $html);
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
    private function render(bool $failed = false, array $cards = [], ?ItemList $items = null): string
    {
        $resolved = new class($failed, $cards)
        {
            /**
             * @param  list<array<string, mixed>>  $cards
             */
            public function __construct(private bool $failed, private array $cards) {}

            public function failed(): bool
            {
                return $this->failed;
            }

            /**
             * @return list<array<string, mixed>>
             */
            public function cards(): array
            {
                return $this->cards;
            }
        };

        return view('meilifacets::components.results', [
            'listing' => fn () => $resolved,
            'itemList' => fn () => $items,
        ])->render();
    }
}
