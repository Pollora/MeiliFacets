# How a listing works

This page explains what `<x-meilifacets::listing>` does, how its components share one search, how the URL carries
the state, and what happens to search engines, to visitors without JavaScript and to the page when Meilisearch is
down.

You want to…

- [understand why every component goes inside the listing](#the-root-and-its-components);
- [point a template at the right listing](#which-listing);
- [read and share a filtered URL](#the-url-is-the-state);
- [choose between instant filtering and an Apply button](#instant-or-on-submit);
- [add a search field that narrows the listing](#the-search-field);
- [know what search engines see](#what-search-engines-see);
- [know what a category or brand archive changes](#the-browsed-term);
- [know what the visitor sees when the engine is down](#when-the-engine-is-down);
- [bring the visitor back to the top after a gesture](#scrolling-back-to-the-top);
- [know what works without JavaScript](#without-javascript).

## Minimal example

On a WooCommerce project, the module declares a product listing named `products`. Nothing has to be written in PHP:
place the components in the Blade template that renders the shop archive.

```blade
<x-meilifacets::listing>
    <x-meilifacets::listing.search />
    <x-meilifacets::listing.facets />
    <x-meilifacets::listing.sort />
    <x-meilifacets::listing.total />
    <x-meilifacets::listing.active-values />
    <x-meilifacets::listing.reset />
    <x-meilifacets::listing.results />
    <x-meilifacets::listing.pagination scroll />
</x-meilifacets::listing>
```

The server renders the first page with the filters of the URL already applied. After that, every check, sort, page or
search is one request from the browser to Meilisearch, and the module rewrites the address bar.

## The root and its components

`<x-meilifacets::listing>` is the root. It:

- renders `<div class="meilifacets" data-listing="products" data-meili-contract="1">` around its slot. Its
  attribute bag lands on that `<div>`, so `class="…"` is merged with the module's class;
- loads the module's stylesheet and, when the browser connection is configured, the browser client
  (see [Installation](../installation.md));
- runs **one** search for the request. Every component of the same listing reads that search, wherever it sits in
  the template: the facets, the results, the total and the pagination never query the engine twice.

Components are free to sit in any order and in any markup of the theme (a sidebar, a toolbar, a grid), as long as
they are **inside** the root. The client binds once per root and only reads what is inside it. A component placed
outside renders, can be checked and styled, and does nothing. The client says so in the browser console when it
starts:

```text
[meilifacets] the client binds inside [data-listing] only. Move inside <x-meilifacets::listing> : facet.
```

| Component | Page |
| --- | --- |
| `listing.facets`, `listing.facet` | [Facets](facets.md) |
| `listing.price` | [Price filter](price.md) |
| `listing.results`, `listing.card`, `listing.sort`, `listing.pagination`, `listing.total`, `listing.active-filters`, `listing.active-values`, `listing.reset`, `listing.apply` | [Results, sorting and pagination](results-sort-pagination.md) |
| `listing.search` | [below](#the-search-field) |
| `listing.drawer`, `listing.drawer-opener` | [Mobile drawer and filter bar](drawer.md) |

Every attribute of every component is listed in the [Blade components reference](../reference/components.md).

## Which listing?

Every listing component takes a `name` attribute: the listing it belongs to. Leave it out when the project declares a
single listing, which is the case of a WooCommerce shop with no other listing.

```blade
<x-meilifacets::listing name="products">
    <x-meilifacets::listing.results name="products" />
</x-meilifacets::listing>
```

The module refuses to guess:

| Situation | Error, raised at render |
| --- | --- |
| no `name`, and no listing declared | `Name the listing: none is declared.` |
| no `name`, and two listings or more declared | `Name the listing: 2 are declared (products, events).` |
| a `name` nobody declared | `No listing named "event". Declared: products, events.` |

Without WooCommerce, the module declares no listing at all: the first message is what a template gets. A listing of
your own is described in [Listing other content](custom-listing.md).

## The URL is the state

The server reads the state from the URL on first render, and the client writes it back after each gesture. A filtered
view can therefore be bookmarked, shared and reloaded.

- **Paths are WordPress's own.** The module keeps the shop and archive paths WooCommerce produces and adds no rewrite
  rule. On a category archive, the category comes from the path, not from a parameter.
- **Filters travel as query parameters**, several values separated by a comma:
  `https://projet.ddev.site/shop/?f_product_brand=acme,globex`.
- **A taxonomy's parameter is `f_<taxonomy>`** unless the project maps it to a readable name. The prefix is there on
  purpose: a bare taxonomy name is a public WordPress query var, and `?product_brand=acme` would make WordPress filter
  its own query as well.
- **The module's own parameters** are `sort`, `q` (search term), `pg` (page number), `min_price` and `max_price`. A
  page served by WordPress under `/page/N` is honoured on first render; the client then writes the page as `pg`.
- **A search term is cut at 200 characters**, and a term with no letter or digit (`?q=%3F%28`) counts as no term.
  The same holds for a WordPress product search routed to the listing (`?s=!&post_type=product`): it shows the
  whole catalogue, where WordPress itself would find nothing.

Give the parameters readable names in the project's `config/meilifacets.php`:

```php
'url_parameters' => [
    'product_cat' => 'category',
    'product_brand' => 'brand',
],

// Only to move a reserved name off a collision.
'query_parameters' => [
    'q' => 'search',
],
```

The URL above becomes `https://projet.ddev.site/shop/?brand=acme,globex`.

Check that no name collides with a WordPress query var or a parameter WooCommerce reads:

```bash
php artisan meilifacets:check-parameters
```

The command fails on a blocking collision and only warns about `min_price` and `max_price`, which keep WooCommerce's
names on purpose (see [Price filter](price.md#bounds-in-the-url)). Run it again after adding a taxonomy, an attribute
or a plugin. See [Commands](../reference/commands.md).

When the client writes the URL, it keeps the query parameters WordPress itself read to build the page (for example
`s` and `post_type` on a product search) and drops everything else, such as `utm_*`. A page change adds an entry to
the browser history; a filter, a sort or a search term replaces the current entry. Back and Forward restore the
listing to the state of that entry.

## Instant or on submit

`apply_mode` decides whether a checked box searches at once or waits for the visitor to apply.

```php
// config/meilifacets.php
'apply_mode' => 'immediate', // or 'submit'
```

| Gesture | `immediate` | `submit` |
| --- | --- | --- |
| check or uncheck a facet value | searches | waits: the change is pending |
| commit a price (field changed, handle released) | searches | waits |
| type in the [search field](#the-search-field) | searches after a pause | waits |
| press Enter in the search field | searches | searches, with every pending change |
| click Apply | nothing to apply (it only closes the [drawer](drawer.md#apply-in-immediate-mode)) | searches, with every pending change |
| pick a sort, change page | searches | searches, with every pending change |
| remove an active value, clear the search field | searches | searches, with every pending change |
| Clear all | searches | searches; pending changes are dropped with the rest |

In `submit` mode, an Apply button is rendered at the end of `<x-meilifacets::listing.facets>` (see
[Results, sorting and pagination](results-sort-pagination.md#apply)). The count on the drawer opener and on the
pill-shaped Apply button follows the pending selection, so the visitor sees what Apply is about to send.

`immediate` costs one search per check and suits a modest catalogue. `submit` costs one search per Apply and suits a
large one.

There is no `<form method="get">` around the facets: a GET form writes `brand[]=acme&brand[]=globex`, a second URL for
a state that `brand=acme,globex` already describes, and a second entry in any page cache.

The setting applies to the product listing. A listing of your own returns its mode from `applyMode()` and may ignore
the setting (see [Listing other content](custom-listing.md#implementing-listing)).

## The search field

`<x-meilifacets::listing.search>` is a search box that narrows the listing to a term. It fills the `q` parameter and
works with the facets, the sort and the price: the counts of every facet take the term into account.

```blade
<x-meilifacets::listing>
    <x-meilifacets::listing.search label="Search the shop" />
    …
</x-meilifacets::listing>
```

| Attribute | Default | Effect |
| --- | --- | --- |
| `label` | `Search this list` (translated) | the name of the `role="search"` landmark, of the field and its placeholder. Keep it different from the site search label so that the two landmarks of the page can be told apart |
| `name` | the only listing | the listing it narrows |

With JavaScript:

- in `immediate` mode, a term searches once the visitor has typed at least 2 characters and paused for 120 ms (the
  same settings as the site search, see [Site search](../search/README.md)). Under the threshold, the term already
  applied is removed;
- in `submit` mode, the term waits like a checked box; Enter or Apply sends it;
- the ✕ button and the active-value pill of the term remove it at once, in both modes;
- the page goes back to 1.

Without JavaScript, the field is a real `<form method="get">`: it sends `q` to the first page of the listing, with the
applied facets, sort and price bounds in hidden fields, so the server renders the filtered page.

The term gets its own pill in `<x-meilifacets::listing.active-values>`, first in the list. Clear all removes it.

On a WordPress search results page (`/?s=…&post_type=product`), the field renders nothing: the term is WordPress's,
and a `q` in the address is ignored there.

Which fields a term is matched against, and in which order, is described in
[Search relevance](../indexing/relevance.md).

To hide the field without removing the term, hide it in CSS (`[data-meili="listing-search"] { display: none; }`): the
term can still be removed with its pill or with Clear all.

## What search engines see

On a page that renders a listing, any view that is not the bare path is a secondary view: a facet value, a sort,
a search term, a price bound, a page number in `pg`, or a `/page/N` path. A secondary view is served
`noindex, follow`, because its content already exists on the bare path, and its links stay followed.

- A secondary view's canonical points to the bare path, without facets, sort, search or price bounds. A page number
  is kept, because page 2 does not list the products of page 1: `/shop/?q=cream&pg=2` points to `/shop/?pg=2`, and
  `/shop/page/2/?brand=acme` to `/shop/page/2/`. The module builds it from the one Yoast SEO prints. Without
  Yoast, the module prints none: an archive has no canonical, and a page keeps the one WordPress prints for it.
- `rel="next"` and `rel="prev"` are removed from every page that renders a listing, bare path included. Product pages
  are found through the sitemap, not through paginated listings.
- A query parameter the module does not own, such as `?utm_source=news`, changes nothing.
- An indexable view carries a JSON-LD `ItemList` of the cards on the page, with their position, URL and title.
  Secondary views carry none.

These rules, and the preconnect hint to the engine, apply on the pages that render `<x-meilifacets::listing>` before
their `<head>`: a view that extends its layout renders its sections first, so its listing is known when the head is
printed. A layout that prints the head first (`get_header()`, then the content) is not covered. Tell the module with
the `meilifacets/is_listing_page` filter, which can also withdraw a page:

```php
add_filter('meilifacets/is_listing_page', fn (bool $isListingPage): bool => $isListingPage || is_page('catalogue'));
```

## The browsed term

On a term archive of a product taxonomy (a category, a brand, a `pa_*` attribute with archives), the path's term
filters the listing: `https://projet.ddev.site/product-category/skincare/` lists the skincare products only. On an
attribute archive the card does not show the matching variant: the module recommends leaving attribute archives off
([Card variants](../customising/card.md#card-variants)).

- A facet of the same taxonomy is not offered any more: it would hold one value, the one the path already filters
  on. It can still be placed, and renders hidden.
- A `ChildTermsFacet` is the exception: on that archive, it offers the terms directly under the current one. See
  [Facets](facets.md#sub-level-navigation).

## When the engine is down

When the first render cannot reach Meilisearch, or the engine refuses the query:

- the whole listing is replaced by a short message, `Search is temporarily unavailable. Please try again in a
  moment.` (`role="alert"`, view `listing.unavailable`, overridable);
- the page is served with status `503`, `Retry-After: 120` and `Cache-Control: no-store`, so that search engines treat
  the failure as temporary and no cache keeps it;
- the failure is reported to the application's exception handler.

When a search from the browser fails, the client keeps what the page shows. Nothing is announced.

An attribute that is not filterable or sortable in the index makes the engine refuse the query, which shows as the
same message: see [Going to production](../production.md).

## Scrolling back to the top

After a gesture that replaces the grid, the page stays where it is by default. Add `scroll` to a component to bring
the top of the listing back into view after a gesture on that component:

```blade
<x-meilifacets::listing.pagination scroll />
<x-meilifacets::listing.reset scroll />
```

| Component | Gesture that scrolls |
| --- | --- |
| `listing.pagination` | a page number, Previous, Next |
| `listing.sort` (default `listbox` widget) | picking an option |
| `listing.reset` | Clear all |
| `listing.facets` | its Apply button |

The scroll is skipped when the gesture came from the keyboard, so that the focused control stays in view. The page's
own `scroll-behavior` decides whether the scroll is smooth.

`listing.facet`, `listing.price`, `listing.active-filters` and `listing.sort widget="radios"` also accept `scroll`,
but no gesture of theirs scrolls: checking a box never moves the page. An Apply button placed on its own takes the
attribute directly: `<x-meilifacets::listing.apply data-meili-scroll />`.

## Without JavaScript

The first render is complete without JavaScript: the server applies every filter, sort, price and page of the URL,
and the results, counts, total and active values are right.

The controls themselves need the client. Facets, sort, price, pagination, Apply and Clear all are buttons and inputs
outside any form, so they do nothing without it. The [search field](#the-search-field) is the exception: it submits
as a regular form. On small screens the [drawer](drawer.md#without-javascript) leaves the filters in the page.

## Watch out

- **Every component inside the root.** A component outside `<x-meilifacets::listing>` renders and stays inert. The
  console message above names it.
- **The client starts only when the browser connection is configured.** Without `browser.url` (with its scheme) and
  `browser.key`, the page renders in full and no control answers. Nothing is logged. See
  [Installation](../installation.md).
- **Renaming a URL parameter breaks every link already shared or indexed** with the old name.
- **Declaring a second listing** makes every component without `name` fail with `Name the listing: …`. Name them all
  before adding one.
- **A listing rendered after the `<head>`** is not covered by the `noindex` rule: declare its page with the
  `meilifacets/is_listing_page` filter ([What search engines see](#what-search-engines-see)).
- **Some components take no attribute bag.** `listing.results`, `listing.facets`, `listing.sort`,
  `listing.pagination`, `listing.reset` and `listing.active-filters` drop a `class` given to them, without error. Wrap
  them, or override their view.

## See also

- [Facets](facets.md), [Price filter](price.md), [Results, sorting and pagination](results-sort-pagination.md),
  [Mobile drawer and filter bar](drawer.md), [Listing other content](custom-listing.md)
- [Blade components](../reference/components.md), [Configuration and environment](../reference/configuration.md),
  [`data-meili` hooks](../reference/hooks.md), [Errors and console messages](../reference/errors.md)
- [Overriding views](../customising/views.md), [Accessibility and motion](../accessibility.md)
