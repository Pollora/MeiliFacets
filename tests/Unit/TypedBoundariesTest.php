<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\TypedBoundaries;

use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Providers\IndexingServiceProvider;
use Modules\MeiliFacets\Search\MeilisearchQuery;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;

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
