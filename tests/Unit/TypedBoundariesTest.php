<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\TypedBoundaries;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Discovery\DeclarationRefused;
use Modules\MeiliFacets\Discovery\ListingRegistry;
use Modules\MeiliFacets\Providers\IndexingServiceProvider;
use Modules\MeiliFacets\Search\EngineAnswer;
use Modules\MeiliFacets\Search\MeilisearchQuery;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use stdClass;

it('never asks the engine for a negative number of hits', function (): void {
    expect(new MeilisearchQuery('posts')->from(['hitsPerPage' => -1])->toArray()['hitsPerPage'])->toBe(0);
});

/** An attribute name is a string: anything else in the configuration would make the engine refuse every setting. */
it('displays the attributes the configuration names, and nothing that is not a name', function (): void {
    WordPress::boot();
    WordPress::$config['meilifacets.displayed_attributes'] = ['sku', 3, null, 'ean'];
    new IndexingServiceProvider(app())->register();

    $displayed = app()->make(IndexAttributes::class)->displayed();

    expect($displayed)->toContain('sku', 'ean')
        ->and($displayed)->not->toContain(3, null);
});

it('reads an answer of the engine, taking what it does not hold as nothing', function (): void {
    $answer = new EngineAnswer([
        'hits' => [3 => ['ID' => 1], 4 => 'junk'],
        'totalHits' => '12',
        'facetDistribution' => ['facets.brand' => ['acme' => 2, 'globex' => 'many'], 'facets.size' => 'junk'],
        'facetStats' => ['price.min' => ['min' => 10, 'max' => 99.5, 'avg' => 'junk'], 'price.max' => 'junk'],
    ]);

    expect($answer->hits())->toBe([['ID' => 1]])
        ->and($answer->total())->toBe(12)
        ->and($answer->distributions())->toBe(['facets.brand' => ['acme' => 2], 'facets.size' => []])
        ->and($answer->distribution('facets.colour'))->toBe([])
        ->and($answer->stats())->toBe(['price.min' => ['min' => 10, 'max' => 99.5], 'price.max' => []])
        ->and([new EngineAnswer()->hits(), new EngineAnswer()->total(), new EngineAnswer()->stats()])->toBe([[], 0, []]);
});

it('reports a declaration that does not build a listing, and keeps the others', function (): void {
    WordPress::boot();
    $registry = new ListingRegistry(new Container);
    $registry->add(new FakeListing(name: 'shop'));
    $registry->addDeclaration(stdClass::class);

    expect($registry->named('shop')->name())->toBe('shop')
        ->and(WordPress::$log[array_key_last(WordPress::$log)][1][0])->toBeInstanceOf(DeclarationRefused::class);
});
