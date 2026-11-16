<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\TermField;

it('reads the text held under its key', function (): void {
    $term = ['slug' => 'acme', 'taxonomy' => 'product_brand'];

    expect(TermField::Slug->textIn($term))->toBe('acme');
    expect(TermField::Taxonomy->textIn($term))->toBe('product_brand');
});

it('reads nothing from a missing or empty key', function (): void {
    expect(TermField::Slug->textIn(['name' => 'Acme']))->toBeNull();
    expect(TermField::Slug->textIn(['slug' => '']))->toBeNull();
});

// A term read from the database carries its ids as numbers, and a broken one may carry anything.
it('reads nothing from a value that is not text', function (): void {
    expect(TermField::TermId->textIn(['term_id' => 12]))->toBeNull();
    expect(TermField::Slug->textIn(['slug' => null]))->toBeNull();
    expect(TermField::Slug->textIn(['slug' => ['acme']]))->toBeNull();
});
