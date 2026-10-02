# PHP contracts

Every interface in `Modules\MeiliFacets\Contracts`, its default implementation, and how the module binds it, so
you know how to replace or decorate it.

You want to…

- [know how the module's bindings behave](#how-the-module-binds);
- [look up a public contract](#public-contracts);
- [look up an advanced contract](#advanced-contracts);
- [find the value classes and helpers the contracts work with](#value-classes-enums-and-helpers).

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Search\BrandCardProjector;
use App\Search\ShopFacets;
use Illuminate\Support\ServiceProvider;
use Modules\MeiliFacets\Contracts\CardProjector;
use Modules\MeiliFacets\Contracts\ProductFacets;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bound by the module with scopedIf: this binding wins.
        $this->app->scoped(ProductFacets::class, ShopFacets::class);

        // A default made of layers: decorate it.
        $this->app->extend(
            CardProjector::class,
            fn (CardProjector $card): CardProjector => new BrandCardProjector($card),
        );
    }
}
```

## How the module binds

| Module binding | Your `bind()` / `scoped()` in `register()` | Your `extend()` |
| --- | --- | --- |
| `bindIf`, `scopedIf` | wins, whatever the order of the providers | decorates the default |
| `bind`, `scoped` (unconditional) | wins only if it runs after the module's provider | decorates the default; the safe way |
| none (discovered or passed as an argument) | not applicable | not applicable |

## Public contracts

| Contract | Default implementation | Module binding | Override with | Explained in |
| --- | --- | --- | --- | --- |
| `ProductFacets` | `Listing\WooCommerceFacets`: category as a `ChildTermsFacet`, then brand, both ordered by `NameOrder` | `scopedIf` | `scoped` | [Facets](../listing/facets.md) |
| `ProductSorts` | `Listing\WooCommerceSorts`: `price_asc`, `price_desc`, `newest`, `on_sale` | `scopedIf` | `scoped` | [Sorting](../listing/results-sort-pagination.md) |
| `CardProjector` | `Indexing\DeferredCardProjector`: with WooCommerce, `WooCommerceCardProjector` (products: default card plus `price`; other posts: summary card); without, `SummaryCardProjector`. Both wrap `DefaultCardProjector` (title, URL, image) | `bindIf` | `extend` to add fields; `bind` to replace | [What gets indexed](../indexing/README.md) |
| `IndexAttributes` | `Indexing\ConfiguredIndexAttributes` (adds `displayed_attributes`) around `DeferredIndexAttributes`: `WooCommerceIndexAttributes` with WooCommerce, `EmptyIndexAttributes` without | `bind` | `extend` | [What gets indexed](../indexing/README.md) |
| `SearchableAttributes` | `Indexing\DefaultSearchableAttributes`: title, then brand, category and SKU (WooCommerce), other labels, excerpt, content | `scopedIf` | `scoped` or `bind`, decorating the default | [Search relevance](../indexing/relevance.md) |
| `SearchableTypes` | `SiteSearch\WooCommerceSearchableTypes`: products first when WooCommerce is active and products are searchable, then `WordPressSearchableTypes` | `scopedIf` | `scoped`, decorating the default | [Searchable types](../search/types.md) |
| `Listing` | `Listing\ProductListing`, named `products`; refuses itself without WooCommerce | discovered | implement it in a class Pollora discovers (`app/`, or a module's `app/`) | [Listing other content](../listing/custom-listing.md) |
| `SearchScopedListing` | `Listing\ProductListing` | discovered | implement it instead of `Listing` | [Listing other content](../listing/custom-listing.md) |
| `ValueOrder` | none bound; `Listing\NameOrder` is provided (bound `scoped`, collates by the site language) | pass it to a `Facet` | implement it | [Facets](../listing/facets.md) |
| `ValuePresentation` | `Enums\Presentation` (`control`, `pill`) | pass it to a `Facet` or a component | implement it, as an enum | [Facets](../listing/facets.md) |
| `Placeable` | `Listing\Facet`, `Listing\PriceFilter` | none | do not implement: the components only place these two | [Facets](../listing/facets.md) |

`SiteSearch\SearchSettings` is a class, not an interface, bound like a contract:

| Class | Default | Module binding | Override with | Explained in |
| --- | --- | --- | --- | --- |
| `SearchSettings` | `new SearchSettings()`: `minChars` `2`, `delay` `120` ms, `limit` `4` | `bindIf` | `bind` | [Site search](../search/README.md) |

### Methods

| Contract | Methods |
| --- | --- |
| `ProductFacets` | `all(): list<Facet\|PriceFilter>` |
| `ProductSorts` | `all(): array<string, Sort>`, keyed as the sort travels in the URL |
| `CardProjector` | `project(WP_Post $post): array<string, mixed>` |
| `IndexAttributes` | `exactlyMatched(): list<string>`, `filterable(): list<string>`, `sortable(): list<string>`, `displayed(): list<string>` |
| `SearchableAttributes` | `all(): list<string>`, most important first |
| `SearchableTypes` | `all(): array<string, SearchableType>`, keyed by post type |
| `Listing` | `name(): string`, `facets(): list<Facet>`, `filters(): list<Placeable>`, `sorts(): array<string, Sort>`, `baseFilter(): list<string>`, `baseQuery(): string`, `perPage(): int`, `applyMode(): ApplyMode` |
| `SearchScopedListing` | the `Listing` methods, plus `searchScope(): SearchScope`: filter and fields used once a term is typed |
| `ValueOrder` | `compare(FacetValue $first, FacetValue $second): int` |
| `ValuePresentation` | `slug(): string` (the `data-presentation` value), `allowsSingleSelection(): bool` |
| `Placeable` | properties `string $name { get; }`, `string $label { get; }` |

## Advanced contracts

Internal seams. All but `FacetCounter` are bound without `If`: decorate them with `extend()`. Replacing one
changes behaviour the rest of the module relies on.

| Contract | Default implementation | Module binding | Used for |
| --- | --- | --- | --- |
| `FacetCounter` | `Search\DisjunctiveFacetCounter` | `bindIf` | the extra queries that count a multi-value facet on the first render; the browser counts on its own |
| `SearchEngine` | `Search\MeilisearchEngine` (MeiliScout's search client, index `posts`) | `scoped` | sends the server's searches; throws `EngineUnavailable` |
| `TermHierarchy` | `Indexing\WordPressTermHierarchy` | `bind` | ancestors of a term at indexing; children for `ChildTermsFacet` |
| `TermLabels` | `Listing\WordPressTermLabels` | `bind` | the names of a facet's values, in the taxonomy's order |
| `TermScope` | `Listing\WordPressTermScope` | `scoped` | the terms under the one the page is on |
| `DefaultTerms` | `Listing\WordPressDefaultTerms` | `scoped` | the fallback term of a taxonomy (“Uncategorized”) |

| Contract | Methods |
| --- | --- |
| `FacetCounter` | `queries(Listing $listing, ListingState $state): array<string, array<string, mixed>>` |
| `SearchEngine` | `multiSearch(array $queries): array`, answers under the caller's keys |
| `TermHierarchy` | `ancestorsOf(int $termId, string $taxonomy): list<array<string, mixed>>`, `childrenOf(int $termId, string $taxonomy): list<string>` |
| `TermLabels` | `of(string $taxonomy, list<string> $slugs): array<string, string>` |
| `TermScope` | `childrenOf(string $taxonomy): list<string>` |
| `DefaultTerms` | `slugOf(string $taxonomy): ?string` |

## Value classes, enums and helpers

All under `Modules\MeiliFacets\`.

| Name | Kind | Role | Explained in |
| --- | --- | --- | --- |
| `Listing\Facet` | class | a term facet: taxonomy, label, selection, order, visible, cap, default term, name, presentation | [Facets](../listing/facets.md) |
| `Listing\ChildTermsFacet` | class | a facet showing the children of the browsed term | [Facets](../listing/facets.md) |
| `Listing\PriceFilter` | class | the price filter: label, parts, name (`price`) | [Price filter](../listing/price.md) |
| `Listing\Sort` | class | a sort: label, Meilisearch sort expressions, optional filter; `Sort::filtering()` | [Sorting](../listing/results-sort-pagination.md) |
| `Listing\SortFilter` | class | the filter a sort carries; `SortFilter::whereTrue()` | [Sorting](../listing/results-sort-pagination.md) |
| `Listing\NameOrder` | class | orders values by name in the site language | [Facets](../listing/facets.md) |
| `Listing\CardVariant` | class | one way a product is sold, inside its card: facets, price, fields; `toArray()`, `read()` | [Card variants](../customising/card.md#card-variants) |
| `Listing\VariantChoice` | class | `shown(array $card)`: the card through the variant the filters point to, as projected when none concerns its variants | [Card variants](../customising/card.md#card-variants) |
| `Listing\ListingUnavailable` | exception | thrown by a listing's constructor to opt out quietly | [Listing other content](../listing/custom-listing.md) |
| `SiteSearch\SearchableType` | class | a searchable type; `withHeading()`, `withSeeAllLabel()`, `withCard()`, `withArchive()`, `withoutArchive()`, `withSearchOn()` | [Searchable types](../search/types.md) |
| `SiteSearch\SearchableTypeFactory` | class | `forPostType()`, `make()` | [Searchable types](../search/types.md) |
| `Enums\SelectionMode` | enum | `Multiple`, `Single` | [Facets](../listing/facets.md) |
| `Enums\DisplayOrder` | enum | `Count`, `Declared` | [Facets](../listing/facets.md) |
| `Enums\DefaultTermVisibility` | enum | `Hidden`, `Shown` | [Facets](../listing/facets.md) |
| `Enums\Presentation` | enum | `Control` (`control`), `Pill` (`pill`) | [Facets](../listing/facets.md) |
| `Enums\PricePart` | enum | `Fields` (`fields`), `Slider` (`slider`) | [Price filter](../listing/price.md) |
| `Enums\ApplyMode` | enum | `OnSubmit` (`submit`), `Immediate` (`immediate`); `ApplyMode::DEFAULT` (`OnSubmit`) | [How a listing works](../listing/README.md) |
| `Enums\HeadingLevel` | enum | `h2` to `h6` | [Blade components](components.md) |
| `Enums\ImagePriority` | enum | `Eager`, `Lazy` | [Blade components](components.md) |
| `Enums\SortWidget`, `Enums\ApplyShape`, `Enums\ResetShape` | enums | component variants | [Blade components](components.md) |
| `Enums\CardField` | enum | card field names | [Index settings](index-settings.md#card-fields) |
| `Enums\VariantField` | enum | the keys of a stored variant | [Index settings](index-settings.md#card-fields) |
| `Enums\DocumentField`, `Enums\PriceField`, `Enums\ProductMeta`, `Enums\SearchedMeta` | enums | document field names and paths | [Index settings](index-settings.md) |
| `Enums\ProductTaxonomy` | enum | WooCommerce taxonomies; `technical()` lists those never searched | [Search relevance](../indexing/relevance.md) |
| `Enums\Hook` | enum | the `data-meili` hooks; `->attribute()` | [`data-meili` hooks](hooks.md) |
| `Search\PublishedPosts` | helper | `of($postType)`: filter clauses for published posts of a type | [Listing other content](../listing/custom-listing.md) |
| `Search\FilterExpression` | helper | `equals()`, `without()`, `all()`: Meilisearch filter clauses | [Listing other content](../listing/custom-listing.md) |
| `Search\VisibleProducts` | helper | `inCatalogue()`, `inSearch()`: clauses for visible products | [Searchable types](../search/types.md) |
| `Support\WooCommerce` | helper | `isActive()` | [Composing the panel](../search/composition.md) |

## Watch out

- Bind in a service provider's `register()`.
- `IndexAttributes` and `SearchEngine` are bound unconditionally: a plain `bind()` of yours can be overwritten by
  the module's. Use `extend()`.
- The defaults ask whether WooCommerce is active on every call. A replacement that calls WooCommerce functions
  checks `Support\WooCommerce::isActive()` itself.
- A change to `IndexAttributes`, `SearchableAttributes` or `CardProjector` reaches the index at the next indexing.
  After adding a `Listing` class, run `php artisan discovery:clear`.

## See also

- [PHP extension points](../customising/php.md)
- [WordPress filters and actions](wordpress-hooks.md)
- [Errors and console messages](errors.md)
