<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\TermAncestry;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermHierarchy;

const TERM_ANCESTRY_CATEGORY_TREE = [
    'category' => [
        60 => ['slug' => 'coats', 'parent' => 55],
        55 => ['slug' => 'outerwear', 'parent' => 50],
        50 => ['slug' => 'clothing', 'parent' => 0],
    ],
];

/**
 * @return array<string, mixed>
 */
function termAncestryTerm(int $id, string $slug, string $taxonomy, int $parent): array
{
    return [
        'term_id' => $id,
        'slug' => $slug,
        'taxonomy' => $taxonomy,
        'parent' => $parent,
    ];
}

beforeEach(function (): void {
    $this->hierarchy = new FakeTermHierarchy(TERM_ANCESTRY_CATEGORY_TREE);
    $this->ancestry = new TermAncestry($this->hierarchy);
});

it('leaves a root term untouched', function (): void {
    $terms = [termAncestryTerm(50, 'clothing', 'category', 0)];

    expect($this->ancestry->expand($terms))->toBe($terms);
    expect($this->hierarchy->lookups)->toBe(0);
});

it('appends the whole chain of a nested term', function (): void {
    $expanded = $this->ancestry->expand([termAncestryTerm(60, 'coats', 'category', 55)]);

    expect(array_column($expanded, 'slug'))->toBe(['coats', 'outerwear', 'clothing']);
});

it('keeps the other terms of the document', function (): void {
    $expanded = $this->ancestry->expand([
        termAncestryTerm(26, 'acme', 'brand', 0),
        termAncestryTerm(55, 'outerwear', 'category', 50),
    ]);

    expect(array_column($expanded, 'slug'))->toBe(['acme', 'outerwear', 'clothing']);
});

it('carries a shared ancestor once', function (): void {
    $expanded = $this->ancestry->expand([
        termAncestryTerm(55, 'outerwear', 'category', 50),
        termAncestryTerm(60, 'coats', 'category', 55),
    ]);

    expect(array_column($expanded, 'slug'))->toBe(['outerwear', 'coats', 'clothing']);
});

it('resolves a shared shelf once across a batch', function (): void {
    $document = [termAncestryTerm(55, 'outerwear', 'category', 50)];

    $this->ancestry->expand($document);
    $this->ancestry->expand($document);

    expect($this->hierarchy->lookups)->toBe(1);
});

it('skips a term without a usable taxonomy', function (): void {
    $terms = [['term_id' => 55, 'slug' => 'outerwear', 'parent' => 50]];

    expect($this->ancestry->expand($terms))->toBe($terms);
    expect($this->hierarchy->lookups)->toBe(0);
});

it('treats an unreadable parent as a root', function (): void {
    $terms = [['term_id' => 55, 'slug' => 'outerwear', 'taxonomy' => 'category']];

    expect($this->ancestry->expand($terms))->toBe($terms);
    expect($this->hierarchy->lookups)->toBe(0);
});

it('reads an identifier written as a string', function (): void {
    $term = ['term_id' => '60', 'slug' => 'coats', 'taxonomy' => 'category', 'parent' => '55'];

    expect(array_column($this->ancestry->expand([$term]), 'slug'))->toBe(['coats', 'outerwear', 'clothing']);
});
