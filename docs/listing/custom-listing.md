# Listing other content

This page explains how to declare a listing of your own, for posts or a custom post type, by implementing the
`Listing` contract.

> **Limitation.** The product listing is the only listing the module ships and the only one proven in production. The
> contract below is public and a listing of your own is rendered by the same components, but no listing of other
> content has been run on a real site yet. The differences known today are listed under [What differs from the
> product listing](#what-differs-from-the-product-listing). Test your listing on your own content before relying on
> it.

You want to…

- [filter a custom post type archive](#minimal-example);
- [know what each method of the contract does](#implementing-listing);
- [know how the module finds your listing](#discovery);
- [filter on the term of a taxonomy archive](#filtering-on-the-archives-term);
- [narrow what a search term reads](#narrowing-what-a-search-reads);
- [know what the cards show](#cards);
- [run two listings on one page](#two-listings-on-one-page).

## Minimal example

An archive of `event` posts, filtered by an `event_type` taxonomy and sorted by date or title:

```php
<?php

declare(strict_types=1);

namespace App\Listing;

use Illuminate\Container\Attributes\Config;
use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Search\PublishedPosts;

final class EventListing implements Listing
{
    public const string NAME = 'events';

    private const string POST_TYPE = 'event';

    public function __construct(
        #[Config('meilifacets.apply_mode', ApplyMode::DEFAULT->value)] private string $applyMode = ApplyMode::DEFAULT->value,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function facets(): array
    {
        return [new Facet('event_type', __('Type'))];
    }

    public function filters(): array
    {
        return $this->facets();
    }

    public function sorts(): array
    {
        return [
            'newest' => new Sort(__('Most recent'), ['post_date:desc']),
            'title' => new Sort(__('Title, A to Z'), ['post_title:asc']),
        ];
    }

    public function baseFilter(): array
    {
        return PublishedPosts::of(self::POST_TYPE);
    }

    public function baseQuery(): string
    {
        return '';
    }

    public function perPage(): int
    {
        return 12;
    }

    public function applyMode(): ApplyMode
    {
        return ApplyMode::tryFrom($this->applyMode) ?? ApplyMode::DEFAULT;
    }
}
```

Clear the discovery cache so that the module finds the new class:

```bash
php artisan discovery:clear
```

Then name the listing in the template of the event archive:

```blade
<x-meilifacets::listing name="events">
    <x-meilifacets::listing.facets name="events" />
    <x-meilifacets::listing.sort name="events" />
    <x-meilifacets::listing.results name="events" />
    <x-meilifacets::listing.pagination name="events" scroll />
</x-meilifacets::listing>
```

The `event` post type must be indexed by MeiliScout, and the index rebuilt, before the listing shows anything. See
[What gets indexed](../indexing/README.md).

## Implementing `Listing`

`Modules\MeiliFacets\Contracts\Listing` has eight methods.

| Method | Returns | Role |
| --- | --- | --- |
| `name()` | `string` | the name templates use in their `name` attribute. Unique among listings |
| `facets()` | `list<Facet>` | the term facets the engine counts and the URL is read for. Only `Facet` and `ChildTermsFacet` objects |
| `filters()` | `list<Placeable>` | everything a template may place, in the order `<x-meilifacets::listing.facets>` renders it: the facets, and a `PriceFilter` if any |
| `sorts()` | `array<string, Sort>` | the sorts on offer, keyed by their value in the `sort` URL parameter. “Relevance” is added first by the module |
| `baseFilter()` | `list<string>` | Meilisearch filter clauses every query of this listing carries, joined with `AND` |
| `baseQuery()` | `string` | the text the page itself searches for. Empty, unless the page is a WordPress search results page your listing serves |
| `perPage()` | `int` | the number of results per page |
| `applyMode()` | `ApplyMode` | `ApplyMode::Immediate` or `ApplyMode::OnSubmit`. To follow `apply_mode` from `config/meilifacets.php`, inject it with `#[Config]` as in the example above |

Rules that keep the methods consistent:

- **Every facet of `facets()` is also in `filters()`.** A facet in `filters()` that is missing from `facets()` can be
  placed, but gets no values and renders hidden. That is how the product listing withdraws a facet on the archive of
  its own taxonomy.
- **Names are unique** across `filters()`: see [Facets](facets.md#naming-facets-with-an-enum).
- **Compute request-dependent values inside the methods**, not in the constructor. The module builds your listing
  once, when discovery runs, before WordPress has parsed the request; the methods are called while the page renders.
- **Sort and filter on declared attributes.** A sort expression needs a sortable attribute, a clause a filterable one.
  `post_type`, `post_status` and every `facets.<taxonomy>` are filterable; `post_title` and `post_date` are sortable.
  See [Index settings and document fields](../reference/index-settings.md). An attribute the index does not declare
  makes the engine refuse the query, and the listing shows the unavailable message.

Classes used above live in `Modules\MeiliFacets\Listing` (`Facet`, `ChildTermsFacet`, `PriceFilter`, `Sort`),
`Modules\MeiliFacets\Enums` (`ApplyMode`) and `Modules\MeiliFacets\Contracts` (`Listing`, `Placeable`). Facets take
every argument described in [Facets](facets.md#the-facet-constructor).

## Discovery

There is nothing to register. Any concrete class implementing `Listing` in the application's `app/` directory, or in
the `app/` directory of a Laravel module, is found by Pollora's discovery and built through the container, so its
constructor can ask for dependencies.

- After adding or renaming a listing class, run `php artisan discovery:clear`.
- A constructor that throws `Modules\MeiliFacets\Listing\ListingUnavailable` withdraws the listing quietly. Use it when
  the listing depends on something that may be missing, such as a plugin. This is what the product listing does
  without WooCommerce:

  ```php
  public function __construct()
  {
      if (! class_exists(\Acme\Events\Plugin::class)) {
          throw new ListingUnavailable('The Acme Events plugin is not active: there are no events to list.');
      }
  }
  ```

  Discovery may run before WordPress fires `init`: test for the plugin itself, a class or a function it defines,
  rather than for a post type it registers on `init`.

- Any other exception thrown while building the listing is reported to the application's exception handler, and the
  listing is skipped. Templates then fail with `No listing named "events". Declared: products.`, and the report says
  why.

## Building the base filter

`baseFilter()` returns clauses in Meilisearch's filter syntax. Two helpers write them safely:

| Helper | Returns |
| --- | --- |
| `PublishedPosts::of('event')` | the two clauses `post_type = "event"` and `post_status = "publish"` |
| `FilterExpression::equals($field, $value)` | one clause `field = "value"`, with the value quoted and escaped |

Both live in `Modules\MeiliFacets\Search`. Write a value read from the request only through `FilterExpression`: a
clause built by concatenation lets a crafted URL rewrite the filter.

### Filtering on the archive's term

On a term archive, the product listing filters on the path's term. A listing of your own does the same in
`baseFilter()`, and leaves the facet of that taxonomy out of `facets()`:

```php
<?php

declare(strict_types=1);

namespace App\Listing;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Enums\ApplyMode;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Search\FilterExpression;
use Modules\MeiliFacets\Search\PublishedPosts;
use WP_Term;

final class EventListing implements Listing
{
    private const string POST_TYPE = 'event';

    private const string TYPE_TAXONOMY = 'event_type';

    public function name(): string
    {
        return 'events';
    }

    public function facets(): array
    {
        return $this->browsedType() instanceof WP_Term ? [] : $this->filters();
    }

    public function filters(): array
    {
        return [new Facet(self::TYPE_TAXONOMY, __('Type'))];
    }

    public function sorts(): array
    {
        return [];
    }

    public function baseFilter(): array
    {
        $type = $this->browsedType();
        $published = PublishedPosts::of(self::POST_TYPE);

        if (! $type instanceof WP_Term) {
            return $published;
        }

        return [...$published, FilterExpression::equals('facets.'.self::TYPE_TAXONOMY, $type->slug)];
    }

    public function baseQuery(): string
    {
        return '';
    }

    public function perPage(): int
    {
        return 12;
    }

    public function applyMode(): ApplyMode
    {
        return ApplyMode::Immediate;
    }

    private function browsedType(): ?WP_Term
    {
        $term = get_queried_object();

        return $term instanceof WP_Term && $term->taxonomy === self::TYPE_TAXONOMY ? $term : null;
    }
}
```

The index stores each taxonomy under `facets.<taxonomy>`, ancestors included for a hierarchical one, so a parent term
lists the content of its children. For a hierarchical taxonomy, a `ChildTermsFacet` keeps the facet and offers the
children of the current term instead (see [Facets](facets.md#sub-level-navigation)).

## Narrowing what a search reads

When a term is searched, through the [search field](README.md#the-search-field) or a WordPress search your listing
serves, the query reads `baseFilter()` and every searchable attribute. A listing that needs something else implements
`Modules\MeiliFacets\Contracts\SearchScopedListing`, which adds one method to `Listing`:

```php
public function searchScope(): SearchScope
{
    return new SearchScope(
        filter: PublishedPosts::of('event'),
        fields: ['post_title', 'labels.event_type'],
    );
}
```

| `SearchScope` argument | Effect while a term is searched |
| --- | --- |
| `filter` | the clauses used **instead of** `baseFilter()` |
| `fields` | the attributes the term is matched against (Meilisearch's `attributesToSearchOn`). `null`, the default, searches every searchable attribute. Each one must be a searchable attribute of the index |

`SearchScope` is `Modules\MeiliFacets\Listing\SearchScope`. The product listing uses it to search the products a
WordPress search may show, which differs from the products the catalogue shows. The searchable attributes are in
[Search relevance](../indexing/relevance.md).

## Cards

The cards of a listing are the `card` field of each document, built at indexing time. For content other than products,
the default card holds the title, the link, the featured image and a summary built from the excerpt or the content
(its length follows WordPress's `excerpt_length` filter).

The module's listing card shows the image and the title. The summary is in the data but not in the default view. To
show it, or to show anything else, fill your own card: see [The card](../customising/card.md). To put more data in
the card, see [PHP extension points](../customising/php.md).

## Two listings on one page

Two listings can be placed on one page if every component names its listing. Their element ids are prefixed with the
listing's name, and each keeps its own state in the browser history.

They share the URL, though:

- the reserved parameters `sort`, `q`, `pg`, `min_price` and `max_price` are the same for every listing: on a reload,
  both listings read the same page number, sort and search term;
- a facet's parameter depends on its taxonomy only, so two listings filtering the same taxonomy read the same values;
- a gesture on one listing rewrites the address with its own state and drops the parameters of the other: the other
  listing's state survives Back and Forward, but not a reload or a shared link.

Two listings on one page are therefore usable when only one of them is driven by the visitor, and not recommended
otherwise.

## What differs from the product listing

| Behaviour | Product listing | Your listing |
| --- | --- | --- |
| `apply_mode` from the configuration | read | read only if your listing injects it, as in the [example](#minimal-example) |
| page size | WooCommerce's `loop_shop_per_page` | what `perPage()` returns |
| facets withdrawn on a term archive | automatic | yours to write in `facets()` ([above](#filtering-on-the-archives-term)) |
| price filter and “On sale” sort | available | not available: the module indexes prices for WooCommerce products only |
| price on the card | shown | none |
| `noindex` on filtered views, preconnect to the engine | on the pages that render the listing | the same |
| search scope on a search page | products a WordPress search may show | `baseFilter()`, unless the listing implements `SearchScopedListing` |

## Watch out

- **A second listing changes every template.** Once two listings are declared, a component with no `name` fails with
  `Name the listing: 2 are declared (products, events).` Name the listing on every component of every template,
  product templates included.
- **Run `php artisan discovery:clear`** after adding the class: a cached discovery does not see it.
- **Index the post type first.** A post type MeiliScout does not index gives an empty listing, without error.
- **Nothing in the constructor that depends on the request.**
- **Facets must be in `facets()` to get values**, not only in `filters()`.

## See also

- [How a listing works](README.md), [Facets](facets.md), [Results, sorting and pagination](results-sort-pagination.md)
- [What gets indexed](../indexing/README.md), [Search relevance](../indexing/relevance.md)
- [PHP contracts](../reference/contracts.md), [Index settings and document fields](../reference/index-settings.md),
  [Commands](../reference/commands.md)
