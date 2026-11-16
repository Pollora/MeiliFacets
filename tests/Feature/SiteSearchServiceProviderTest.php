<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\Providers\SiteSearchServiceProvider;
use Modules\MeiliFacets\SiteSearch\SearchSettings;
use Modules\MeiliFacets\Tests\Feature\Concerns\RegistersAProvider;
use Tests\TestCase;

trait SiteSearchServiceProviderHelpers
{
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

/** The default declaration of searchable types is pinned by `SearchableTypesTest`. */
uses(TestCase::class, RegistersAProvider::class, SiteSearchServiceProviderHelpers::class);

it('keeps the searchable types a project bound first', function (): void {
    $types = $this->projectTypes();
    $container = $this->registered(
        SiteSearchServiceProvider::class,
        static fn (Container $project) => $project->instance(SearchableTypes::class, $types)
    );

    expect($container->make(SearchableTypes::class))->toBe($types);
});

it('searches with the default settings when the project binds none', function (): void {
    $settings = $this->registered(SiteSearchServiceProvider::class)->make(SearchSettings::class);

    expect($settings)->toEqual(new SearchSettings);
});

it('keeps the search settings a project bound first', function (): void {
    $settings = new SearchSettings()->withMinChars(3);
    $container = $this->registered(
        SiteSearchServiceProvider::class,
        static fn (Container $project) => $project->instance(SearchSettings::class, $settings)
    );

    expect($container->make(SearchSettings::class))->toBe($settings);
});
