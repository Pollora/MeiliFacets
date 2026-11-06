# Results, sorting and pagination

This page covers the components around the grid: the results and their cards, the sort, the pagination, the total,
the active filters, Clear all and Apply.

You want to…

- [show the results, and know what the empty states say](#results);
- [change the card, or control which images load first](#the-card);
- [change the sorts on offer or their labels](#sorting);
- [show the sort as radios](#sort-widgets);
- [paginate](#pagination);
- [show the number of results](#total);
- [show the active filters, or removable pills](#active-filters);
- [add a Clear all button](#clear-all);
- [place an Apply button](#apply).

The search field that narrows the results is described in
[How a listing works](README.md#the-search-field).

## Minimal example

A toolbar above the grid, the grid, and the pagination under it:

```blade
<x-meilifacets::listing>
    <div class="shop-toolbar">
        <x-meilifacets::listing.total />
        <x-meilifacets::listing.active-values />
        <x-meilifacets::listing.reset shape="pill" />
        <x-meilifacets::listing.sort />
    </div>

    <x-meilifacets::listing.results />
    <x-meilifacets::listing.pagination />
</x-meilifacets::listing>
```

Every component takes `name`, the listing it belongs to, which can be left out when the project declares a single
listing (see [How a listing works](README.md#which-listing)).

## Results

`<x-meilifacets::listing.results />` renders:

- the cards of the current page, in a list;
- an empty state, shown when the page holds no card. It reads `No results found.` when nothing matches, and
  `There is nothing on this page.` when results exist but the page asked for is past the last one;
- a `<template>` holding the same card with no data, which the client fills for each hit after a gesture;
- on an indexable view, a JSON-LD `ItemList` of the cards (see
  [How a listing works](README.md#what-search-engines-see)).

While a search is under way, the list carries `aria-busy="true"`. The stylesheet dims it only if the answer takes
longer than a short delay, so a quick answer never flickers.

When the engine is down on first render, the component renders the unavailable message instead. Otherwise it renders
that message hidden, on the `unavailable` hook, for the client to show when a later search fails (see
[How a listing works](README.md#when-the-engine-is-down)).

`listing.results` takes no attribute bag: a `class` given to it is lost. Style the list through its parent, or
override `components/listing/results.blade.php` (see [Overriding views](../customising/views.md)).

## The card

The results render each card with `<x-meilifacets::listing.card>`, unless the listing names its own (see
[A card per listing](../customising/card.md#a-card-per-listing)). The default card shows the image, the title as a
link, and, for a WooCommerce product, the price as the shop formats it. Its data is the `card` field of the indexed
document, written at indexing time: the browser never asks WordPress for anything.

| Attribute | Type | Default | Effect |
| --- | --- | --- | --- |
| `binding` | `CardBinding` | required | `CardBinding::of($card)`, the card data of one document |
| `heading` | `h2` to `h6`, or a `HeadingLevel` case | `h3` | the level of the title |
| `priority` | `ImagePriority` | `ImagePriority::Lazy` | `Eager` loads the image at once with `fetchpriority="high"`, `Lazy` defers it |

The attribute bag lands on the `<article>`.

Filling your theme's own card from the indexed data, overriding the card view, or putting more data in the card is
covered in [The card](../customising/card.md). What the card holds at indexing time, and the image size, are in
[What gets indexed](../indexing/README.md).

### Which images load first

The first cards of the page load their image eagerly, with a high fetch priority; the next ones load lazily. Set how
many in `config/meilifacets.php`:

```php
'card' => [
    'eager' => 4,
],
```

Match it to the number of cards visible above the fold on the most common screen. Too low, and the largest image of
the page is deferred by the lazy loading meant to help it. Too high, and it competes for bandwidth with images nobody
sees yet.

## Sorting

`<x-meilifacets::listing.sort />` renders a “Sort by” control.

| Default sort | URL value | Order |
| --- | --- | --- |
| Relevance | none | the engine's own order under a searched term; otherwise [the default order](#the-default-order). Always first, added by the module |
| Price, low to high | `price_asc` | by the lowest price of each product |
| Price, high to low | `price_desc` | by the highest price of each product |
| New arrivals | `newest` | by publication date, newest first |
| On sale | `on_sale` | products on sale only, in [the default order](#the-default-order) |

Once a variation attribute is checked, a price sort ranks each product by its matching variant's price, the variants
in stock first.

- “Relevance” cannot be removed or moved; its label is translatable like the others.
- “On sale” is offered only by a listing that declares a [price filter](price.md), and it is hidden while no matching
  product is on sale, unless it is the sort in force.
- Picking a sort searches at once, in both `apply_mode` values, with the pending changes in `submit` mode.
- The control is not rendered when there is a single choice.

### The default order

The shop's order is chosen in WordPress, not in code: **Appearance › Customize › WooCommerce › Product Catalog ›
Default product sorting**. While no sort is chosen and no term is searched, the product listing follows that setting,
on the first page as on every page the visitor turns to:

| Default product sorting | Order |
| --- | --- |
| Default sorting (custom ordering + name) | the order set by drag and drop in Products › Sorting, then title |
| Sort by most recent | publication date, newest first |
| Sort by price (asc) | by the lowest price of each product |
| Sort by price (desc) | by the highest price of each product |

- « Popularity (sales) » and « Average rating » are not offered: the module removes them from the list, since the
  index holds neither sales nor ratings as a sortable field. They are not supported for now.
- A theme can answer the setting in code with the `woocommerce_default_catalog_orderby` filter. `title`,
  `date-asc` and the other `field-direction` values WooCommerce reads are followed; anything else (`popularity`,
  `rating`, `rand`, `modified`, `id`) keeps the engine's order.
- Products published in the same second are not ordered by ID, as WooCommerce orders them: their order among
  themselves is the engine's.
- Under a filter on a variation attribute (a size, for instance), the products keep the same order.
- A sort that only filters, such as « On sale », keeps the default order.
- Reordering products by drag and drop in Products › Sorting reindexes the products that moved.
- Changing the setting takes effect at once. `menu_order` becomes sortable when the index settings are written: run
  `wp meiliscout index` once after updating the module.
- A listing of your own picks its default order with [`DefaultSortedListing`](custom-listing.md#a-default-order).

### Changing the sorts

Bind your own `ProductSorts` in a project service provider. Start from the module's list rather than rewriting it.
This one drops “New arrivals” and adds an alphabetical order:

```php
<?php

declare(strict_types=1);

namespace App\Listing;

use Illuminate\Support\Arr;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Listing\WooCommerceSorts;

final class CatalogueSorts implements ProductSorts
{
    public function __construct(private readonly WooCommerceSorts $defaults) {}

    public function all(): array
    {
        return [
            ...Arr::except($this->defaults->all(), ['newest']),
            'name' => new Sort(__('Name, A to Z'), ['post_title:asc']),
        ];
    }
}
```

```php
// In the register() method of a project service provider
$this->app->scoped(\Modules\MeiliFacets\Contracts\ProductSorts::class, \App\Listing\CatalogueSorts::class);
```

The keys of `all()` are the values of the `sort` parameter in the URL, in the order the choices are offered. Renaming
a key breaks the links that carry it.

| Constructor | Arguments |
| --- | --- |
| `new Sort($label, $expressions, $filter = null)` | a translated label, and a list of Meilisearch sort expressions such as `['post_date:desc']` |
| `Sort::filtering($label, $filter)` | a translated label, and a `SortFilter`: the sort keeps only the documents matching it, in the listing's default order |
| `new SortFilter($field, $value)` | the document field and the value it must equal |
| `SortFilter::whereTrue($field)` | the document field, which must be `true` |

`Sort` and `SortFilter` live in `Modules\MeiliFacets\Listing`. A sort expression needs a sortable attribute, and a
filtering sort a filterable one. Sortable out of the box: `post_title` and `post_date` (from MeiliScout), `price.min`,
`price.max` and `in_stock` (from the module, with WooCommerce), and the meta keys MeiliScout is set to index, as
`metas.<key>`. An attribute the index does not declare makes the engine refuse the query: the whole listing then shows
the unavailable message. See [Index settings and document fields](../reference/index-settings.md).

A filtering sort is hidden while it would match nothing, like “On sale”.

The list applies to the product listing as a whole, on every page that shows it.

### Sort widgets

| Attribute | Default | Effect |
| --- | --- | --- |
| `widget` | `listbox` | `listbox`: a button that opens a list of choices (the ARIA combobox pattern, with the full keyboard support it promises). `radios`: a `<fieldset>` of radio buttons |
| `collapsible` | `false` | with `widget="radios"` only: the fieldset folds like a [collapsible facet](facets.md#collapsible-facets) |
| `scroll` | `false` | with `listbox`: picking a choice brings the top of the listing back into view |
| `label` | none | names the sort in place of “Sort by” (see below) |

```blade
<x-meilifacets::listing.sort widget="radios" collapsible />
```

The collapsible radios show the sort in force in their toggle: “Sort by: Relevance”. The sentence comes from the
translatable key `Sort by: :choice`, so a language can put the choice anywhere in it. A sort is an order, not a
filter: its toggle never shows a count.

A theme gives its own wording with `label`, as on the listing search, the module's keys not being overridable from a
theme's catalogue:

- `label="Sort"`, with no `:choice`, names the sort alone everywhere: the listbox label, the radios' legend and
  their toggle, in the row as in the sheet. The order in force is not repeated in the toggle;
- `label="Order: :choice"` replaces the sentence of the collapsible radios, the only widget that shows one; their
  section is still named “Sort by”. The listbox label and the legend of plain radios read “Sort by”, and the sentence
  appears nowhere.

```blade
<x-meilifacets::listing.sort widget="radios" collapsible :label="__('Sort')" />
```

Render the sort once per page, whatever its widget. A second one fails:

```text
The sort of listing "products" is rendered twice on this page: its list and ids would be duplicated. Render <x-meilifacets::listing.sort> once per page.
```

`listing.sort` takes no attribute bag.

## Pagination

`<x-meilifacets::listing.pagination />` renders Previous, up to seven page numbers around the current page, and Next.

- **Page size.** The product listing reads WooCommerce's: products per row times rows per page, through the
  `loop_shop_per_page` filter. Change it there, and the module follows.
- **The last page** is the last one the engine can serve: Meilisearch serves at most `engine.reachable_hits` results
  (default `1000`), so with 24 per page, page 42 is the last one offered, however many products match. The total and
  the facet counts stay exact. Raise the value in `config/meilifacets.php`, then reindex. See
  [Configuration and environment](../reference/configuration.md).
- **Each page is a page of its own.** The controls are links to the path WordPress paginates under,
  `https://projet.ddev.site/shop/page/2`. The server renders that address whole, and crawlers follow the links. Each
  link keeps the listing's filters, sort and search, and the query parameters WordPress reads; anything else in the
  address, such as `utm_source`, is left out.
- **A click turns the page in place.** The client searches in the browser, shows the new page and adds a history
  entry for its address, so Back returns to the page before. A click with Ctrl, Cmd, Shift or Alt held, or with the
  middle button, is left to the browser, which opens the address; so is a link an override points at another tab,
  another site or a download. A click on the current page does nothing. Without JavaScript the links load the page.
- **In `submit` mode**, the page turned to is the one of the filters applied: boxes ticked and not applied are left
  behind, as the link's address says.
- **After a filter or a sort**, the client rewrites the links for the new state, and replaces the current history
  entry.
- **The tab's title, the canonical and the robots directives follow the page.** See
  [The title and the canonical of a turned page](#the-title-and-the-canonical-of-a-turned-page).
- **`scroll`** on `<x-meilifacets::listing.pagination>` brings the top of the listing back into view after a page
  click. See [How a listing works](README.md#scrolling-back-to-the-top).
- **`?pg=N`**, the page parameter of earlier versions, is no longer read: the page shows the first page.
- **Permalinks are required.** Without them WordPress paginates in `?paged=N`; `meilifacets:check-parameters` fails.
- The pagination renders hidden when there is a single page, so that the client can fill it after a gesture.

| Attribute | Default | Effect |
| --- | --- | --- |
| `name` | the only listing | the listing it belongs to |

`listing.pagination` takes no attribute bag.


### The title and the canonical of a turned page

The client writes the tab's title and the canonical link on each page it turns to, as the server prints them on
the page it renders. The server hands it, through the `PageMetadata` contract, the first page's, and a later page's
with `:page` for its number (and `:pages` for the page count, in a title).

- **WordPress alone**: the titles are those of `wp_get_document_title()`, the page part, `Page 2`, after the title.
  WordPress prints a canonical on a singular page only, the same on every page of it.
- **Yoast SEO**: the titles and canonicals are Yoast's.
  - `%%page%%` and `%%pagetotal%%` count the pages the engine answered, on the page the server renders too:
    `/shop/page/2?min_price=20` reads `Shop - Page 2 of 3`, where WordPress, which ignores the filters, counts four.
  - An archive's canonical follows the page, `/shop/page/3`. A custom canonical, or a singular page's, stays the
    same on every page.
  - When a `wpseo_canonical` filter prints a canonical the module cannot tell, the canonical is left alone.
- **Another plugin that writes the head** (Rank Math, SEOPress…): the module cannot tell what it writes, so the page
  keeps what the server printed. Bind your own `PageMetadata` to follow it:

```php
use Modules\MeiliFacets\Contracts\PageMetadata;

final readonly class RankMathPageMetadata implements PageMetadata
{
    public function titles(): ?array
    {
        return ['first' => 'Shop - Site', 'paged' => 'Shop - Page :page of :pages - Site'];
    }

    public function canonicals(): ?array
    {
        return ['first' => 'https://example.com/shop', 'paged' => 'https://example.com/shop/page/:page'];
    }
}

// In a service provider's register():
$this->app->bind(PageMetadata::class, RankMathPageMetadata::class);
```

The robots directives need no contract: every plugin writes them through WordPress's `wp_robots` filter. The server
prints them for the bare view and for any other through `wp_robots()` itself, the module's `noindex, follow` and the
plugins' own included, and the client writes one or the other: the bare view, with no filter, sort, search or page
past the first, keeps `index`.

Each method returns `null` for what it cannot tell. Only the listing rendered first writes them; on a page with
several, the others leave the head alone. Crawlers never turn a page in place: they read the head the server prints
for each address.

## Total

`<x-meilifacets::listing.total />` renders the number of results, `24 items`, from the translatable plural key
`:count item|:count items`. The attribute bag lands on the visible `<p>`.

It is followed by an empty live region that announces the new total to screen readers after each answer. While the
visitor types in the search field, the announcement waits for a pause in the answers, so the voice does not cover the
typing. An override of the view keeps both hooks, `total` and `total-status`: without the second, the number updates
and nothing is announced.

## Active filters

Two components sum up what is selected.

| Component | Renders |
| --- | --- |
| `<x-meilifacets::listing.active-filters />` | the number of active filters in words, `2 active filters`, hidden at zero |
| `<x-meilifacets::listing.active-values />` | one removable pill per active value: the search term first, then each facet value, then the price range |

Each facet value counts as one filter, and a price range counts as one whatever its ends. The search term has a pill
but is not counted.

`active-filters` follows the pending selection in `submit` mode, like the count of the drawer opener.
`active-values` follows what the engine answered: its pills match the results on screen.

A pill reads the value's label (`Acme`, `“cream”` for a search term, `€20.00 – €80.00` for a range). Its accessible
name is `Remove the Acme filter`. Clicking it removes the value and searches at once. A value in the URL that the page
has no label for gets no pill, so that nobody can write on the page through the URL.

`active-values` takes an attribute bag, which lands on its `<div>`. `active-filters` takes none.

The `<div>` holds a visually hidden « Active filters », then the `<ul>` of pills, styled by `[data-meili="active-values"] > ul`.
The client draws each pill where the `<template>` sits and removes it as the child of the template's parent: an
override keeps the `<template>` inside the element that holds the pills.

## Clear all

`<x-meilifacets::listing.reset />` renders a “Clear all” button, hidden while there is nothing to clear. It clears
every facet value, the price range, the search term, the sort and the page, and searches at once, in both modes.
In `immediate` mode it appears and disappears with the pills, once the engine answers; in `submit` mode it follows what
the visitor ticks, so that a selection not applied yet can already be cleared.

| Attribute | Default | Effect |
| --- | --- | --- |
| `shape` | `text` | `text`: a plain text button. `pill`: the same text, drawn like the facet pills. `icon`: a round button with a trash icon, named “Clear all” for screen readers |
| `scroll` | `false` | clearing brings the top of the listing back into view |
| `name` | the only listing | the listing it belongs to |

The `icon` shape takes an `icon` slot. A slot with content replaces the default icon; an empty slot removes it:

```blade
<x-meilifacets::listing.reset shape="icon">
    <x-slot:icon><svg viewBox="0 0 24 24" width="20" height="20">…</svg></x-slot:icon>
</x-meilifacets::listing.reset>
```

The slot is hidden from screen readers. An icon that carries meaning of its own needs an override of
`components/listing/reset-icon.blade.php`.

When the button hides itself after a click, the focus moves to the nearest Apply button, or to the drawer's title, or
to the listing itself: never to the top of the document.

`listing.reset` takes no attribute bag.

## Apply

`<x-meilifacets::listing.apply />` sends the pending changes in `submit` mode (see
[How a listing works](README.md#instant-or-on-submit)).

| Attribute | Default | Effect |
| --- | --- | --- |
| `shape` | `pill` | `pill`: “Apply”, with the number of active filters in a badge. `block`: “Apply filters”, full width, no count |
| `visible-in-drawer` | `false` | renders the button in `immediate` mode too, visible only inside the mobile drawer, where it closes the drawer |
| `scroll` | `false` | the search it runs brings the top of the listing back into view |
| `name` | the only listing | the listing it belongs to |

- In `submit` mode, the button is rendered and visible everywhere.
- In `immediate` mode, it is rendered only with `visible-in-drawer`, and then only seen in the drawer as a bottom
  sheet. See [Mobile drawer and filter bar](drawer.md#apply-in-immediate-mode).
- `<x-meilifacets::listing.facets />` renders a `block` Apply of its own in `submit` mode. Pass
  `:with-apply="false"` to it when you place one elsewhere.

Several Apply buttons on one page are fine: Apply is a command, not a control that holds a state. The attribute bag
lands on the `<button>`.

## Watch out

- **Sort once per page.** Two `listing.sort` components fail, even with different widgets.
- **A sort on an attribute the index does not declare** turns the whole listing into the unavailable message. Declare
  the attribute, then reindex.
- **Pages past `engine.reachable_hits`** are never offered. A visitor who types `/page/500` sees WordPress's 404 when WordPress counts fewer pages, and the
  past-the-end empty state otherwise.
- **Some components take no attribute bag** (`results`, `sort`, `pagination`, `reset`, `active-filters`): a `class`
  is dropped without error.

## See also

- [How a listing works](README.md), [Facets](facets.md), [Price filter](price.md),
  [Mobile drawer and filter bar](drawer.md)
- [The card](../customising/card.md), [Overriding views](../customising/views.md),
  [Translating the interface](../customising/translations.md)
- [Blade components](../reference/components.md), [Configuration and environment](../reference/configuration.md),
  [Accessibility and motion](../accessibility.md)
