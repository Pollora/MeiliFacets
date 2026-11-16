<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Providers\RenderingServiceProvider;
use Modules\MeiliFacets\Tests\Feature\Concerns\RegistersAProvider;
use Modules\MeiliFacets\View\CardSettings;
use Tests\TestCase;

trait RenderingServiceProviderSteps
{
    private const string CARD = 'meilifacets.card';

    private const string EAGER = 'eager';

    /** @var array<string, mixed> */
    private array $hostCard = [];

    private function cardSettings(): CardSettings
    {
        return $this->registered(RenderingServiceProvider::class)->make(CardSettings::class);
    }
}

// Its middleware is pinned by `ServiceUnavailableHeadersTest`; the host's card settings are put back after each test.
uses(TestCase::class, RegistersAProvider::class, RenderingServiceProviderSteps::class);

beforeEach(function (): void {
    $this->hostCard = (array) config(self::CARD, []);
});

afterEach(function (): void {
    config([self::CARD => $this->hostCard]);
});

it('loads the default number of cards eagerly when the project configures none', function (): void {
    $card = $this->hostCard;
    unset($card[self::EAGER]);
    config([self::CARD => $card]);

    expect($this->cardSettings()->eager)->toBe(CardSettings::DEFAULT_EAGER);
});

it('loads as many cards eagerly as the project configures', function (): void {
    config([self::CARD => [...$this->hostCard, self::EAGER => 2]]);

    expect($this->cardSettings()->eager)->toBe(2);
});
