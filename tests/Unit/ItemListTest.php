<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Seo\ItemList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ItemListTest extends TestCase
{
    #[Test]
    public function it_numbers_items_from_the_page_offset(): void
    {
        $list = new ItemList([$this->card('First'), $this->card('Second')], 12);

        $elements = json_decode($list->toJson(), true)['itemListElement'];

        $this->assertSame(13, $elements[0]['position']);
        $this->assertSame(14, $elements[1]['position']);
    }

    #[Test]
    public function it_skips_a_card_without_a_url(): void
    {
        $list = new ItemList([[CardField::Title->value => 'Orphan'], $this->card('Kept')]);

        $elements = json_decode($list->toJson(), true)['itemListElement'];

        $this->assertCount(1, $elements);
        $this->assertSame('Kept', $elements[0]['name']);
    }

    #[Test]
    public function it_is_empty_when_no_card_carries_a_url(): void
    {
        $this->assertTrue((new ItemList([]))->isEmpty());
        $this->assertTrue((new ItemList([[CardField::Title->value => 'Orphan']]))->isEmpty());
        $this->assertFalse((new ItemList([$this->card('Kept')]))->isEmpty());
    }

    #[Test]
    public function it_escapes_markup_so_a_value_cannot_close_the_script_element(): void
    {
        $list = new ItemList([$this->card('</script><script>alert(1)</script>')]);

        $this->assertStringNotContainsString('<', $list->toJson());
        $this->assertStringNotContainsString('>', $list->toJson());
    }

    /**
     * @return array<string, string>
     */
    private function card(string $title): array
    {
        return [
            CardField::Title->value => $title,
            CardField::Url->value => 'https://example.test/'.md5($title),
        ];
    }
}
