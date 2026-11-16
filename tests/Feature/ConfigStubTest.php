<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Enums\RowOverflow;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\View\CardSettings;
use Modules\MeiliFacets\View\Components\Listing\Drawer;
use Tests\TestCase;

trait ConfigStubHelpers
{
    private const string STUB = __DIR__.'/../../config/meilifacets.php.stub';

    /**
     * @return array<string, mixed>
     */
    private function stub(): array
    {
        return require self::STUB;
    }
}

/** Runs here rather than standalone: the stub reads `env()`, which needs a package only a host installs. */
uses(TestCase::class, ConfigStubHelpers::class);

it('publishes the apply mode the code defaults to', function (): void {
    expect($this->stub()['apply_mode'])->toBe(ApplyMode::DEFAULT->value);
});

it('publishes the card settings the code defaults to', function (): void {
    expect($this->stub()['card'])->toBe(['eager' => CardSettings::DEFAULT_EAGER]);
});

it('publishes the row settings the drawer defaults to', function (): void {
    expect($this->stub()['drawer'])->toBe(['row' => ['limit' => Drawer::ROW_LIMIT, 'overflow' => RowOverflow::DEFAULT->value]]);
});

it('publishes the engine limits the code defaults to', function (): void {
    expect($this->stub()['engine'])->toBe([
        'reachable_hits' => EngineLimits::DEFAULT_REACHABLE_HITS,
        'max_facet_values' => EngineLimits::DEFAULT_MAX_FACET_VALUES,
    ]);
});

it('publishes no renamed parameter and no displayed attribute', function (): void {
    $stub = $this->stub();

    expect($stub['url_parameters'])->toBe([]);
    expect($stub['query_parameters'])->toBe([]);
    expect($stub['displayed_attributes'])->toBe([]);
});
