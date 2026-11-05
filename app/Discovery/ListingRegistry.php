<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Discovery;

use Illuminate\Contracts\Container\Container;
use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Listing\ListingUnavailable;
use Modules\MeiliFacets\Support\NamedRegistry;
use Throwable;

/**
 * @extends NamedRegistry<Listing>
 */
final class ListingRegistry extends NamedRegistry
{
    /** @var array<class-string<Listing>, true> */
    private array $declarations = [];

    public function __construct(private readonly Container $container) {}

    public function add(Listing $listing): void
    {
        $this->entries[$listing->name()] = $listing;
    }

    /**
     * Built on first lookup: Pollora may apply a module's discovery before WordPress loads its plugins.
     *
     * @param  class-string<Listing>  $listingClass
     */
    public function addDeclaration(string $listingClass): void
    {
        $this->declarations[$listingClass] = true;
    }

    protected function beforeLookup(): void
    {
        $this->buildDeclarations();
    }

    private function buildDeclarations(): void
    {
        foreach (array_keys($this->declarations) as $listingClass) {
            $this->build($listingClass);
        }

        $this->declarations = [];
    }

    /**
     * @param  class-string<Listing>  $listingClass
     */
    private function build(string $listingClass): void
    {
        try {
            $this->add($this->container->make($listingClass));
        } catch (ListingUnavailable) {
            return;
        } catch (Throwable $failure) {
            // Unreported, a throwing project binding reads as "no listing is declared"; a throwing handler must not take the page down.
            rescue(static fn () => report($failure), report: false);
        }
    }

    protected function kind(): string
    {
        return 'listing';
    }
}
