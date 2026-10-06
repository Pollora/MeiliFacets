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
    <x-meilifacets::listing.pagination scroll />
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

When the engine is down on first render, the component renders the unavailable message instead (see
[How a listing works](README.md#when-the-engine-is-down)).

`listing.results` takes no attribute bag: a `class` given to it is lost. Style the list through its parent, or
override `components/listing/results.blade.php` (see [Overriding views](../customising/views.md)).

## The card

The results render each card with `<x-meilifacets::listing.card>`. The default card shows the image, the title as a
link, and, for a WooCommerce product, the price as the shop formats it. Its data is the `card` field of the indexed
document, written at indexing time: the browser never asks WordPress for anything.

| Attribute | Type | Default | Effect |
| --- | --- | --- | --- |
| `card` | `array` | required | the card data of one document |
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
| Relevance | none | the engine's own order. Always first, added by the module |
| Price, low to high | `price_asc` | by the lowest price of each product |
| Price, high to low | `price_desc` | by the highest price of each product |
| New arrivals | `newest` | by publication date, newest first |
| On sale | `on_sale` | products on sale only, in the engine's order |

Once a variation attribute is checked, a price sort ranks each product by its matching variant's price, the variants
in stock first.

- “Relevance” cannot be removed or moved; its label is translatable like the others.
- “On sale” is offered only by a listing that declares a [price filter](price.md), and it is hidden while no matching
  product is on sale, unless it is the sort in force.
- Picking a sort searches at once, in both `apply_mode` values, with the pending changes in `submit` mode.
- The control is not rendered when there is a single choice.

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
| `Sort::filtering($label, $filter)` | a translated label, and a `SortFilter`: the sort keeps only the documents matching it, in the engine's order |
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

```blade
<x-meilifacets::listing.sort widget="radios" collapsible />
```

The collapsible radios show the sort in force in their toggle: “Sort by: Relevance”. The sentence comes from the
translatable key `Sort by: :choice`, so a language can put the choice anywhere in it. A sort is an order, not a
filter: its toggle never shows a count.

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
- **History.** A page change adds an entry to the browser history: Back returns to the previous page. A filter or a
  sort replaces the current entry instead.
- **The URL** carries the page as `pg`: `https://projet.ddev.site/shop/?pg=2`.
- The pagination renders hidden when there is a single page, so that the client can fill it after a gesture.

The controls are buttons, not links: nothing in the listing announces a navigation that does not happen. They do
nothing without JavaScript (see [How a listing works](README.md#without-javascript)).

| Attribute | Default | Effect |
| --- | --- | --- |
| `name` | the only listing | the listing it belongs to |
| `scroll` | `false` | a page change brings the top of the listing back into view |

`listing.pagination` takes no attribute bag.

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

`active-values` takes an attribute bag, which lands on its `<ul>`. `active-filters` takes none.

## Clear all

`<x-meilifacets::listing.reset />` renders a “Clear all” button, hidden while there is nothing to clear. It clears
every facet value, the price range, the search term, the sort and the page, and searches at once, in both modes.

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
- **Pages past `engine.reachable_hits`** are never offered. A visitor who types `?pg=500` sees the past-the-end
  empty state.
- **Some components take no attribute bag** (`results`, `sort`, `pagination`, `reset`, `active-filters`): a `class`
  is dropped without error.

## See also

- [How a listing works](README.md), [Facets](facets.md), [Price filter](price.md),
  [Mobile drawer and filter bar](drawer.md)
- [The card](../customising/card.md), [Overriding views](../customising/views.md),
  [Translating the interface](../customising/translations.md)
- [Blade components](../reference/components.md), [Configuration and environment](../reference/configuration.md),
  [Accessibility and motion](../accessibility.md)
