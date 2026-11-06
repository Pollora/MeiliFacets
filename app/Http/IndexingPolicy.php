<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Http;

use Modules\MeiliFacets\Support\UrlParameters;
use Pollora\Attributes\Filter;

/**
 * What a listing URL declares to search engines. A parameterised view shows the
 * same catalogue as its bare path, in another order or another slice.
 */
final readonly class IndexingPolicy
{
    public function __construct(private UrlParameters $parameters, private ListingPage $page) {}

    /**
     * @param  array<string, bool|string>  $robots
     * @return array<string, bool|string>
     */
    #[Filter('wp_robots')]
    public function noindexSecondaryViews(array $robots): array
    {
        return $this->isSecondaryView() ? $this->asSecondaryView($robots) : $robots;
    }

    /**
     * @param  array<string, bool|string>  $robots
     * @return array<string, bool|string>
     */
    public function asSecondaryView(array $robots): array
    {
        unset($robots['index']);

        return [...$robots, 'noindex' => true, 'follow' => true];
    }

    /**
     * Yoast chains a listing to its paginated twins, and each twin to the next;
     * the pagination links to every page already.
     */
    #[Filter('wpseo_next_rel_link')]
    public function dropLinkToTheNextPage(string $link): string
    {
        return $this->page->isCurrent() ? '' : $link;
    }

    #[Filter('wpseo_prev_rel_link')]
    public function dropLinkToThePreviousPage(string $link): string
    {
        return $this->page->isCurrent() ? '' : $link;
    }

    /**
     * Any listing parameter marks a view of a page that already exists on its
     * bare path: a facet, a sort, a search term or a price bound.
     *
     * @param  array<string, mixed>  $query
     */
    public function appliesTo(array $query): bool
    {
        return array_any($query, fn ($value, $parameter): bool => $this->parameters->owns((string) $parameter) && $this->isFilled($value));
    }

    /**
     * A view of a listing that is not its bare path: filtered, sorted, searched,
     * or one of the pages WordPress serves under `/page/N`. The page guard is
     * what keeps a campaign link carrying `?q=` from deindexing the home page.
     */
    public function isSecondaryView(): bool
    {
        return $this->page->isCurrent() && ($this->appliesTo(request()->query()) || is_paged());
    }

    private function isFilled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
