<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Support\UrlParameters;

it('owns its mapped reserved and prefixed names and nothing else', function (): void {
    $parameters = new UrlParameters(['product_brand' => 'marque'], ['sort' => 'tri']);

    expect(array_map($parameters->owns(...), ['marque', 'tri', 'f_product_cat', 'sort', 'utm_source']))
        ->toBe([true, true, true, false, false]);
});

it('gives a mapped taxonomy the configured name', function (): void {
    $parameters = new UrlParameters(['product_brand' => 'marque']);

    expect($parameters->for('product_brand'))->toBe('marque');
});

it('prefixes a taxonomy left unmapped', function (): void {
    $parameters = new UrlParameters([]);

    expect($parameters->for('product_cat'))->toBe('f_product_cat');
});

it('never hands back a bare taxonomy name', function (): void {
    $parameters = new UrlParameters(['product_brand' => 'marque']);

    foreach (['product_cat', 'product_tag', 'collection', 'highlight'] as $reserved) {
        expect($parameters->for($reserved))->not->toBe($reserved);
    }
});
