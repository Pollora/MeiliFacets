<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\TermGrouping;

use Modules\MeiliFacets\Enums\TermField;
use Modules\MeiliFacets\Indexing\TermGrouping;

/**
 * @return array<string, string>
 */
function term(string $taxonomy, string $slug, string $name): array
{
    return [
        TermField::Taxonomy->value => $taxonomy,
        TermField::Slug->value => $slug,
        TermField::Name->value => $name,
    ];
}

it('lists the kept field under each taxonomy in the order met', function (): void {
    $grouped = new TermGrouping(TermField::Slug)->group([
        term('pa_colour', 'red', 'Red'),
        term('product_cat', 'soaps', 'Soaps'),
        term('pa_colour', 'blue', 'Blue'),
    ]);

    expect($grouped)->toBe(['pa_colour' => ['red', 'blue'], 'product_cat' => ['soaps']]);
});

it('reads the field it was built to keep', function (): void {
    $grouped = new TermGrouping(TermField::Name)->group([term('pa_colour', 'red', 'Red')]);

    expect($grouped)->toBe(['pa_colour' => ['Red']]);
});

it('keeps a repeated value once as a list', function (): void {
    $grouped = new TermGrouping(TermField::Slug)->group([
        term('pa_colour', 'red', 'Red'),
        term('pa_colour', 'blue', 'Blue'),
        term('pa_colour', 'red', 'Red'),
        term('pa_colour', 'green', 'Green'),
    ]);

    expect($grouped)->toBe(['pa_colour' => ['red', 'blue', 'green']]);
});

it('keeps a value shared by two taxonomies in both', function (): void {
    $grouped = new TermGrouping(TermField::Slug)->group([
        term('pa_colour', 'red', 'Red'),
        term('pa_finish', 'red', 'Red'),
    ]);

    expect($grouped)->toBe(['pa_colour' => ['red'], 'pa_finish' => ['red']]);
});

it('leaves out a term with no taxonomy or no kept value', function (): void {
    $grouped = new TermGrouping(TermField::Slug)->group([
        [TermField::Slug->value => 'orphan'],
        term('', 'blank-taxonomy', 'Blank'),
        [TermField::Taxonomy->value => 'pa_colour', TermField::Name->value => 'Slugless'],
        term('pa_colour', '', 'Empty slug'),
        [TermField::Taxonomy->value => 'pa_colour', TermField::Slug->value => 42],
    ]);

    expect($grouped)->toBe([]);
});

it('groups nothing out of no term', function (): void {
    expect(new TermGrouping(TermField::Slug)->group([]))->toBe([]);
});
