<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Disclosure;

use Modules\MeiliFacets\View\Badge;
use Modules\MeiliFacets\View\Disclosure;

it('reads its label aloud by default', function (): void {
    expect(new Disclosure('Brand', 'panel-brand', new Badge('count', 2))->readsLabelAloud())->toBeTrue();
});

it('keeps a silent label shown but not read and carries no badge', function (): void {
    $disclosure = Disclosure::withSilentLabel('Sort by', 'panel-sort');

    expect($disclosure->readsLabelAloud())->toBeFalse();
    expect($disclosure->label)->toBe('Sort by');
    expect($disclosure->panelId)->toBe('panel-sort');
    expect($disclosure->badge)->toBeNull();
});
