<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\DefaultSortedListing;
use Modules\MeiliFacets\Enums\ApplyMode;

/** A listing in an order of its own, the rest delegated to a plain fake. */
final readonly class FakeDefaultSortedListing implements DefaultSortedListing
{
    /**
     * @param  list<string>  $defaultSort
     */
    public function __construct(
        private FakeListing $listing,
        private array $defaultSort,
    ) {}

    public function defaultSort(): array
    {
        return $this->defaultSort;
    }

    public function name(): string
    {
        return $this->listing->name();
    }

    public function facets(): array
    {
        return $this->listing->facets();
    }

    public function filters(): array
    {
        return $this->listing->filters();
    }

    public function sorts(): array
    {
        return $this->listing->sorts();
    }

    public function baseFilter(): array
    {
        return $this->listing->baseFilter();
    }

    public function baseQuery(): string
    {
        return $this->listing->baseQuery();
    }

    public function perPage(): int
    {
        return $this->listing->perPage();
    }

    public function applyMode(): ApplyMode
    {
        return $this->listing->applyMode();
    }
}
