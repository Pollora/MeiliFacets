<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Http;

use Modules\MeiliFacets\Support\UrlParameters;
use Pollora\Attributes\Filter;

final readonly class RobotsPolicy
{
    public function __construct(private UrlParameters $parameters) {}

    /**
     * @param  array<string, bool>  $robots
     * @return array<string, bool>
     */
    #[Filter('wp_robots')]
    public function noindexFilteredUrls(array $robots): array
    {
        if (! $this->appliesTo(request()->query())) {
            return $robots;
        }

        unset($robots['index']);

        return [...$robots, 'noindex' => true, 'follow' => true];
    }

    /**
     * Only a facet marks a filtered view. Sort, search and page are left out on
     * purpose: a paginated page must stay indexable, or the products it holds
     * lose their only internal link.
     *
     * @param  array<string, mixed>  $query
     */
    public function appliesTo(array $query): bool
    {
        foreach ($query as $parameter => $value) {
            if ($this->isFacet((string) $parameter) && $this->isFilled($value)) {
                return true;
            }
        }

        return false;
    }

    private function isFacet(string $parameter): bool
    {
        return str_starts_with($parameter, UrlParameters::UNMAPPED_PREFIX)
            || in_array($parameter, $this->parameters->taxonomies(), true);
    }

    private function isFilled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
