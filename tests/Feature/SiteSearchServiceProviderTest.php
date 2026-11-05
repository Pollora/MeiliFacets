<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Providers\SiteSearchServiceProvider;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The default declaration of searchable types is pinned by `SearchableTypesTest`. */
final class SiteSearchServiceProviderTest extends TestCase
{
    use RegistersAProvider;

    #[Test]
    public function it_keeps_the_searchable_types_a_project_bound_first(): void
    {
        $types = $this->projectTypes();
        $container = $this->registered(
            SiteSearchServiceProvider::class,
            static fn (Container $project) => $project->instance(SearchableTypes::class, $types)
        );

        $this->assertSame($types, $container->make(SearchableTypes::class));
    }

    #[Test]
    public function it_searches_with_the_default_settings_when_the_project_binds_none(): void
    {
        $settings = $this->registered(SiteSearchServiceProvider::class)->make(SearchSettings::class);

        $this->assertEquals(new SearchSettings, $settings);
    }

    #[Test]
    public function it_keeps_the_search_settings_a_project_bound_first(): void
    {
        $settings = new SearchSettings()->withMinChars(3);
        $container = $this->registered(
            SiteSearchServiceProvider::class,
            static fn (Container $project) => $project->instance(SearchSettings::class, $settings)
        );

        $this->assertSame($settings, $container->make(SearchSettings::class));
    }

    private function projectTypes(): SearchableTypes
    {
        return new readonly class implements SearchableTypes
        {
            public function all(): array
            {
                return [];
            }
        };
    }
}
