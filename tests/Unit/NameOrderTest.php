<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Collator;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\NameOrder;

function nameOrderCollator(): Collator
{
    $collator = new Collator('fr_FR');
    $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);

    return $collator;
}

/**
 * @param  list<string>  $labels
 * @return list<string>
 */
function nameOrderOrdered(array $labels, ?Collator $collator): array
{
    $values = array_map(
        static fn (string $label): FacetValue => new FacetValue($label, $label, 1, false, false),
        $labels
    );

    usort($values, new NameOrder($collator instanceof Collator ? fn (): Collator => $collator : null)->compare(...));

    return array_map(static fn (FacetValue $value): string => $value->label, $values);
}

it('reads accents as the language does', function (): void {
    expect(nameOrderOrdered(
        ['Diffuseurs', 'Démaquillants', 'Dissolvants', 'Déodorants', 'Exfoliants', 'Écrans', 'Épilation'],
        nameOrderCollator()
    ))->toBe(['Démaquillants', 'Déodorants', 'Diffuseurs', 'Dissolvants', 'Écrans', 'Épilation', 'Exfoliants']);
})->requires('intl');

// Without `NUMERIC_COLLATION`, ICU reads the digits as text and puts `100ml` first.
it('reads a number as a number', function (): void {
    expect(nameOrderOrdered(['100ml', '9ml', '10ml'], nameOrderCollator()))->toBe(['9ml', '10ml', '100ml']);
})->requires('intl');

// A label that is not valid UTF-8 makes `Collator::compare()` answer `false`, which is not an order.
it('orders what the collator refuses to read', function (): void {
    $broken = "\xC3\x28";

    expect(nameOrderOrdered([$broken, 'a'], nameOrderCollator()))->toBe(['a', $broken]);
})->requires('intl');

it('falls back on a host without the extension', function (): void {
    expect(nameOrderOrdered(['100ml', '9ml', '10ml'], null))->toBe(['9ml', '10ml', '100ml']);
});
