<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\StylesheetNeutrality;

// The module carries behaviour, the theme carries appearance: its stylesheet names roles, never a shop's colours.

const STYLESHEETS = __DIR__.'/../../resources/assets/css';

function source(string $stylesheet): string
{
    return (string) file_get_contents(STYLESHEETS.'/'.$stylesheet);
}

it('writes no colour by its value', function (string $stylesheet): void {
    preg_match_all('/#[0-9a-f]{3,8}\b|\brgba?\(|\bhsla?\(/i', source($stylesheet), $colours);

    expect($colours[0])->toBe([]);
})->with('StylesheetNeutrality stylesheets');

it('names no typeface', function (string $stylesheet): void {
    expect(source($stylesheet))->not->toMatch('/font-family\s*:/');
})->with('StylesheetNeutrality stylesheets');

it('never lets a box drop out of the tree', function (string $stylesheet): void {
    expect(source($stylesheet))->not->toMatch('/display\s*:\s*contents/');
})->with('StylesheetNeutrality stylesheets');

dataset('StylesheetNeutrality stylesheets', function () {
    yield 'listing' => ['meilifacets.css'];
    yield 'site search' => ['site-search.css'];
});
