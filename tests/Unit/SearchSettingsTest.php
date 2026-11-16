<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\SearchSettings;

use Modules\MeiliFacets\SiteSearch\SearchSettings;

it('waits for two characters and 120 ms and shows four results', function (): void {
    $settings = new SearchSettings;

    expect([$settings->minChars, $settings->delay, $settings->limit])->toBe([2, 120, 4]);
});

it('changes one setting at a time', function (): void {
    $settings = new SearchSettings(3, 200, 6);

    expect($settings->withMinChars(1))->toEqual(new SearchSettings(1, 200, 6));
    expect($settings->withDelay(0))->toEqual(new SearchSettings(3, 0, 6));
    expect($settings)->toEqual(new SearchSettings(3, 200, 6));
});
