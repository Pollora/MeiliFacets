<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Container\Container;
use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Contracts\TermHierarchy;
use Modules\MeiliFacets\Contracts\VariantFields;
use Modules\MeiliFacets\Indexing\DefaultSearchableAttributes;
use Modules\MeiliFacets\Indexing\DeferredCardProjector;
use Modules\MeiliFacets\Indexing\WordPressTermHierarchy;
use Modules\MeiliFacets\Providers\IndexingServiceProvider;
use Modules\MeiliFacets\Tests\Feature\Concerns\RegistersAProvider;
use Modules\MeiliFacets\Tests\Unit\Doubles\ExcerptFirstSearchableAttributes;
use Tests\TestCase;
use WC_Product_Variation;
use WooCommerce;
use WP_Post;

trait IndexingServiceProviderSteps
{
    private function projectCard(): CardProjector
    {
        return new readonly class implements CardProjector
        {
            public function project(WP_Post $post): array
            {
                return [];
            }
        };
    }

    private function projectVariantFields(): VariantFields
    {
        return new readonly class implements VariantFields
        {
            public function project(WC_Product_Variation $variation): array
            {
                return [];
            }
        };
    }
}

uses(TestCase::class, RegistersAProvider::class, IndexingServiceProviderSteps::class);

it('reads the term hierarchy from wordpress', function (): void {
    $container = $this->registered(IndexingServiceProvider::class);

    expect($container->make(TermHierarchy::class))->toBeInstanceOf(WordPressTermHierarchy::class);
});

it('projects cards through its own default when the project binds none', function (): void {
    $container = $this->registered(IndexingServiceProvider::class);

    expect($container->make(CardProjector::class))->toBeInstanceOf(DeferredCardProjector::class);
});

it('keeps the card projector a project bound first', function (): void {
    $card = $this->projectCard();
    $container = $this->registered(
        IndexingServiceProvider::class,
        static fn (Container $project) => $project->instance(CardProjector::class, $card)
    );

    expect($container->make(CardProjector::class))->toBe($card);
});

it('adds no field to a variant when the project binds none', function (): void {
    if (! class_exists(WooCommerce::class)) {
        $this->markTestSkipped('Variants are WooCommerce\'s.');
    }

    $fields = $this->registered(IndexingServiceProvider::class)->make(VariantFields::class);

    expect($fields->project(new WC_Product_Variation))->toBe([]);
});

it('keeps the variant fields a project bound first', function (): void {
    $fields = $this->projectVariantFields();
    $container = $this->registered(
        IndexingServiceProvider::class,
        static fn (Container $project) => $project->instance(VariantFields::class, $fields)
    );

    expect($container->make(VariantFields::class))->toBe($fields);
});

it('orders the searched fields once per request when the project binds none', function (): void {
    $container = $this->registered(IndexingServiceProvider::class);
    $order = $container->make(SearchableAttributes::class);

    expect($order)->toBeInstanceOf(DefaultSearchableAttributes::class);
    expect($container->make(SearchableAttributes::class))->toBe($order);
});

it('keeps the search order a project bound first', function (): void {
    $container = $this->registered(
        IndexingServiceProvider::class,
        static fn (Container $project) => $project->scoped(SearchableAttributes::class, ExcerptFirstSearchableAttributes::class)
    );

    expect($container->make(SearchableAttributes::class))->toBeInstanceOf(ExcerptFirstSearchableAttributes::class);
});
