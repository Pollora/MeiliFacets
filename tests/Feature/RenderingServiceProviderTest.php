<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Providers\RenderingServiceProvider;
use Modules\MeiliFacets\Tests\Feature\Concerns\RegistersAProvider;
use Modules\MeiliFacets\View\CardSettings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Its middleware is pinned by `ServiceUnavailableHeadersTest`; the host's card settings are put back after each test. */
final class RenderingServiceProviderTest extends TestCase
{
    use RegistersAProvider;

    private const string CARD = 'meilifacets.card';

    private const string EAGER = 'eager';

    /** @var array<string, mixed> */
    private array $hostCard = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostCard = (array) config(self::CARD, []);
    }

    protected function tearDown(): void
    {
        config([self::CARD => $this->hostCard]);

        parent::tearDown();
    }

    #[Test]
    public function it_loads_the_default_number_of_cards_eagerly_when_the_project_configures_none(): void
    {
        $card = $this->hostCard;
        unset($card[self::EAGER]);
        config([self::CARD => $card]);

        $this->assertSame(CardSettings::DEFAULT_EAGER, $this->cardSettings()->eager);
    }

    #[Test]
    public function it_loads_as_many_cards_eagerly_as_the_project_configures(): void
    {
        config([self::CARD => [...$this->hostCard, self::EAGER => 2]]);

        $this->assertSame(2, $this->cardSettings()->eager);
    }

    private function cardSettings(): CardSettings
    {
        return $this->registered(RenderingServiceProvider::class)->make(CardSettings::class);
    }
}
