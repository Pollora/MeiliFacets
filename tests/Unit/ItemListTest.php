<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Seo\ItemList;

/**
 * @return array<string, string>
 */
function itemListCard(string $title): array
{
    return [
        CardField::Title->value => $title,
        CardField::Url->value => 'https://example.test/'.md5($title),
    ];
}

it('numbers items from the page offset', function (): void {
    $list = new ItemList([itemListCard('First'), itemListCard('Second')], 12);

    $elements = json_decode($list->toJson(), true)['itemListElement'];

    expect($elements[0]['position'])->toBe(13);
    expect($elements[1]['position'])->toBe(14);
});

it('skips a card without a url', function (): void {
    $list = new ItemList([[CardField::Title->value => 'Orphan'], itemListCard('Kept')]);

    $elements = json_decode($list->toJson(), true)['itemListElement'];

    expect($elements)->toHaveCount(1);
    expect($elements[0]['name'])->toBe('Kept');
});

it('is empty when no card carries a url', function (): void {
    expect(new ItemList([])->isEmpty())->toBeTrue();
    expect(new ItemList([[CardField::Title->value => 'Orphan']])->isEmpty())->toBeTrue();
    expect(new ItemList([itemListCard('Kept')])->isEmpty())->toBeFalse();
});

it('escapes markup so a value cannot close the script element', function (): void {
    $list = new ItemList([itemListCard('</script><script>alert(1)</script>')]);

    expect($list->toJson())->not->toContain('<');
    expect($list->toJson())->not->toContain('>');
});
