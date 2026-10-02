<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Http;

use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Support\UrlParameters;
use Pollora\Attributes\Filter;

/**
 * What a listing URL declares to search engines. A parameterised view shows the
 * same catalogue as its bare path, in another order or another slice.
 */
final readonly class IndexingPolicy
{
    /** Yoast's own WooCommerce integration filters the shop canonical at 10, after us. */
    private const int AFTER_YOAST_WOOCOMMERCE = 20;

    public function __construct(private UrlParameters $parameters, private ListingPage $page) {}

    /**
     * @param  array<string, bool>  $robots
     * @return array<string, bool>
     */
    #[Filter('wp_robots')]
    public function noindexSecondaryViews(array $robots): array
    {
        if (! $this->isSecondaryView()) {
            return $robots;
        }

        unset($robots['index']);

        return [...$robots, 'noindex' => true, 'follow' => true];
    }

    #[Filter('wpseo_canonical', priority: self::AFTER_YOAST_WOOCOMMERCE)]
    public function canonicalizeSecondaryViews(string $canonical): string
    {
        return $this->isSecondaryView() ? $this->canonicalFor($canonical, request()->query()) : $canonical;
    }

    /**
     * Yoast already strips the parameters it does not know, and keeps `/page/N`:
     * only the module's own page number is missing.
     *
     * @param  array<string, mixed>  $query
     */
    public function canonicalFor(string $canonical, array $query): string
    {
        $parameter = $this->parameters->reserved(QueryParameter::Page);
        $page = $this->pageBeyondTheFirst($query[$parameter] ?? null);

        if ($canonical === '' || $page === null) {
            return $canonical;
        }

        $separator = str_contains($canonical, '?') ? '&' : '?';

        return $canonical.$separator.http_build_query([$parameter => $page]);
    }

    /**
     * Yoast chains a listing to its paginated twins, and each twin to the next.
     * That chain is the only way in: nothing else links to `/page/2`, and the
     * sitemap holds every product on its own URL.
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
     * bare path: a facet, a sort, a search term, a price bound or a page number.
     *
     * @param  array<string, mixed>  $query
     */
    public function appliesTo(array $query): bool
    {
        $declared = $this->parameters->all();

        return array_any($query, fn ($value, $parameter): bool => $this->isListingParameter((string) $parameter, $declared) && $this->isFilled($value));
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

    /**
     * @param  list<string>  $declared
     */
    private function isListingParameter(string $parameter, array $declared): bool
    {
        return str_starts_with($parameter, UrlParameters::UNMAPPED_PREFIX)
            || in_array($parameter, $declared, true);
    }

    private function pageBeyondTheFirst(mixed $value): ?int
    {
        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $page = (int) $value;

        return $page > ListingState::FIRST_PAGE ? $page : null;
    }

    private function isFilled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
