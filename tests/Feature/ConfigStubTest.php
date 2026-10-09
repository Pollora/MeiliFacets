<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\View\CardSettings;
use Modules\MeiliFacets\View\Components\Listing\Drawer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Runs here rather than standalone: the stub reads `env()`, which needs a package only a host installs. */
final class ConfigStubTest extends TestCase
{
    private const string STUB = __DIR__.'/../../config/meilifacets.php.stub';

    #[Test]
    public function it_publishes_the_apply_mode_the_code_defaults_to(): void
    {
        $this->assertSame(ApplyMode::DEFAULT->value, $this->stub()['apply_mode']);
    }

    #[Test]
    public function it_publishes_the_card_settings_the_code_defaults_to(): void
    {
        $this->assertSame(
            ['eager' => CardSettings::DEFAULT_EAGER],
            $this->stub()['card']
        );
    }

    #[Test]
    public function it_publishes_the_row_limit_the_drawer_defaults_to(): void
    {
        $this->assertSame(['row_limit' => Drawer::ROW_LIMIT], $this->stub()['drawer']);
    }

    #[Test]
    public function it_publishes_the_engine_limits_the_code_defaults_to(): void
    {
        $this->assertSame(
            [
                'reachable_hits' => EngineLimits::DEFAULT_REACHABLE_HITS,
                'max_facet_values' => EngineLimits::DEFAULT_MAX_FACET_VALUES,
            ],
            $this->stub()['engine']
        );
    }

    #[Test]
    public function it_publishes_no_renamed_parameter_and_no_displayed_attribute(): void
    {
        $stub = $this->stub();

        $this->assertSame([], $stub['url_parameters']);
        $this->assertSame([], $stub['query_parameters']);
        $this->assertSame([], $stub['displayed_attributes']);
    }

    /**
     * @return array<string, mixed>
     */
    private function stub(): array
    {
        return require self::STUB;
    }
}
