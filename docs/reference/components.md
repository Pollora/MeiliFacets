# Blade components

Every tag the module registers under the `meilifacets` prefix, with its attributes, slots and view.

You want to…

- [see every tag at a glance](#index);
- [know the rules every listing or search component shares](#shared-rules);
- [look up one listing component](#listing-components);
- [look up one search component](#search-components);
- [know which views are not tags](#sub-views-and-variant-views).

```blade
<x-meilifacets::listing>
    <x-meilifacets::listing.sort widget="radios" />
    <x-meilifacets::listing.facets collapsible />
    <x-meilifacets::listing.results />
    <x-meilifacets::listing.pagination scroll />
</x-meilifacets::listing>

<x-meilifacets::search :min-chars="3" />
```

## Index

Classes live in `Modules\MeiliFacets\View\Components`. Views live in `resources/views/` of the module, and are
overridden from `<theme>/resources/views/modules/meilifacets/` under the same path.

| Tag | Class | View | Explained in |
| --- | --- | --- | --- |
| `<x-meilifacets::listing>` | `Listing` | `components/listing.blade.php` | [How a listing works](../listing/README.md) |
| `<x-meilifacets::listing.results>` | `Listing\Results` | `components/listing/results.blade.php` | [Results](../listing/results-sort-pagination.md) |
| `<x-meilifacets::listing.card>` | `Listing\Card` | `components/listing/card.blade.php` | [Results](../listing/results-sort-pagination.md) |
| `<x-meilifacets::listing.card-template>` | `Listing\CardTemplate` | `components/listing/card.blade.php` | [Results](../listing/results-sort-pagination.md) |
| `<x-meilifacets::listing.facets>` | `Listing\Facets` | `components/listing/facets.blade.php` | [Facets](../listing/facets.md) |
| `<x-meilifacets::listing.facet>` | `Listing\Facet` | `components/listing/facet.blade.php` | [Facets](../listing/facets.md) |
| `<x-meilifacets::listing.price>` | `Listing\Price` | `components/listing/price.blade.php` | [Price filter](../listing/price.md) |
| `<x-meilifacets::listing.sort>` | `Listing\Sort` | `components/listing/sort.blade.php`, `components/listing/sort-radios.blade.php` | [Sorting](../listing/results-sort-pagination.md) |
| `<x-meilifacets::listing.pagination>` | `Listing\Pagination` | `components/listing/pagination.blade.php` | [Pagination](../listing/results-sort-pagination.md) |
| `<x-meilifacets::listing.total>` | `Listing\Total` | `components/listing/total.blade.php` | [Total](../listing/results-sort-pagination.md) |
| `<x-meilifacets::listing.active-filters>` | `Listing\ActiveFilters` | `components/listing/active-filters.blade.php` | [Active filters](../listing/results-sort-pagination.md) |
| `<x-meilifacets::listing.active-values>` | `Listing\ActiveValues` | `components/listing/active-values.blade.php` | [Active filters](../listing/results-sort-pagination.md) |
| `<x-meilifacets::listing.reset>` | `Listing\Reset` | `components/listing/reset.blade.php`, `components/listing/reset-icon.blade.php` | [Reset](../listing/results-sort-pagination.md) |
| `<x-meilifacets::listing.apply>` | `Listing\Apply` | `components/listing/apply.blade.php` | [Apply](../listing/results-sort-pagination.md) |
| `<x-meilifacets::listing.search>` | `Listing\Search` | `components/listing/search.blade.php` | [How a listing works](../listing/README.md) |
| `<x-meilifacets::listing.drawer>` | `Listing\Drawer` | `components/listing/drawer.blade.php` | [Mobile drawer](../listing/drawer.md) |
| `<x-meilifacets::listing.drawer-opener>` | `Listing\DrawerOpener` | `components/listing/drawer-opener.blade.php` | [Mobile drawer](../listing/drawer.md) |
| `<x-meilifacets::listing.unavailable>` | `Listing\Unavailable` | `components/listing/unavailable.blade.php` | [How a listing works](../listing/README.md) |
| `<x-meilifacets::listing.toggle>` | anonymous | `components/listing/toggle.blade.php` | [Facets](../listing/facets.md) |
| `<x-meilifacets::listing.price.range>` | anonymous | `components/listing/price/range.blade.php` | [Price filter](../listing/price.md) |
| `<x-meilifacets::listing.price.fields>` | anonymous | `components/listing/price/fields.blade.php` | [Price filter](../listing/price.md) |
| `<x-meilifacets::listing.price.hidden>` | anonymous | `components/listing/price/hidden.blade.php` | [Price filter](../listing/price.md) |
| `<x-meilifacets::search>` | `Search` | `components/search.blade.php` | [Site search](../search/README.md) |
| `<x-meilifacets::search.toggle>` | `Search\Toggle` | `components/search/toggle.blade.php` | [Composing the panel](../search/composition.md) |
| `<x-meilifacets::search.panel>` | `Search\Panel` | `components/search/panel.blade.php` | [Composing the panel](../search/composition.md) |
| `<x-meilifacets::search.input>` | `Search\Input` | `components/search/input.blade.php` | [Composing the panel](../search/composition.md) |
| `<x-meilifacets::search.section>` | `Search\Section` | `components/search/section.blade.php` | [Composing the panel](../search/composition.md) |
| `<x-meilifacets::search.card>` | `Search\Card` | `components/search/card.blade.php` | [Searchable types](../search/types.md) |
| `<x-meilifacets::search.empty-state>` | `Search\EmptyState` | `components/search/empty-state.blade.php` | [Composing the panel](../search/composition.md) |
| `<x-meilifacets::search.unavailable>` | `Search\Unavailable` | `components/search/unavailable.blade.php` | [Composing the panel](../search/composition.md) |

## Shared rules

| Rule | Applies to | Detail |
| --- | --- | --- |
| `name` | every listing component except `listing.card`, `listing.card-template` and `listing.unavailable` | `string`, default `''`: the only listing declared on the site. With none or several declared, an empty `name` throws (see [errors](errors.md#listing-and-search-names)). |
| `name` | `search.toggle`, `search.panel`, `search.input`, `search.section` | `string`, default `''`: the only `<x-meilifacets::search>` rendered so far on the page. |
| `scroll` | `listing.facets`, `listing.facet`, `listing.price`, `listing.sort`, `listing.pagination`, `listing.reset` | `bool`, default `false`. Renders `data-meili-scroll`: a pointer click on a control inside brings the top of the listing back into view. See [hooks](hooks.md#version-and-scroll-attributes). |
| Attribute bag | per component, column “Bag lands on” | Attributes the class does not declare (`class`, `id`, `data-*`…) are merged on that element. “Nowhere” means they are dropped without a message. |
| Variant attributes | `heading`, `shape`, `widget`, `presentation` | Accept the string value, or the enum case when bound (`:shape="ResetShape::Icon"`). An unknown string throws a `ValueError`. |
| Boolean attributes | `scroll`, `collapsible`, `with-apply`, `visible-in-drawer` | Write the bare attribute for `true`; bind (`:with-apply="false"`) for `false`. |
| Inside the root | every listing brick, every search brick | A brick must sit inside its root element (`<x-meilifacets::listing>` or `<x-meilifacets::search>`): outside it, it renders but the client never binds it. |

## Listing components

### `<x-meilifacets::listing>`

The root. It renders `data-listing` and `data-meili-contract`, asks for the `meilifacets` stylesheet and the
`@meilifacets/listing` script module, and runs the one search every brick inside reads. When the engine fails, it
renders `<x-meilifacets::listing.unavailable>` instead of its slot.

| Attribute | Type | Default | Values |
| --- | --- | --- | --- |
| `name` | `string` | `''` | a declared listing name, for example `products` |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| default | root `<div class="meilifacets">` | none (the root is found by `data-listing`) |

### `<x-meilifacets::listing.results>`

The empty-state messages, the result list, the card template and, on an indexable view, a JSON-LD `ItemList`.
Renders `<x-meilifacets::listing.unavailable>` when the engine failed. The first `card.eager` cards load eagerly
(see [configuration](configuration.md#module-settings)).

| Attribute | Type | Default |
| --- | --- | --- |
| `name` | `string` | `''` |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | nowhere | `empty`, `no-results`, `past-the-end`, `results`, `card`, `card-template` |

### `<x-meilifacets::listing.card>`

One result card, rendered on the server. The same view serves the card template.

| Attribute | Type | Default | Values |
| --- | --- | --- | --- |
| `card` | `array<string, mixed>` | required | the `card` field of a document (see [index settings](index-settings.md#card-fields)) |
| `heading` | `HeadingLevel` or `string` | `h3` | `h2`, `h3`, `h4`, `h5`, `h6` |
| `priority` | `ImagePriority` | `ImagePriority::Lazy` | `ImagePriority::Eager`, `ImagePriority::Lazy`; bind it (`:priority`), no string form |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | `<article>` | `url` (on the `<a>`), `image`, `title`, `price` |

The view receives `$binding` (a `CardBinding`: `text()`, `price()`, `attributes()`, `classes()`, `classList()`,
`onlyWith()`, `onlyWithout()`), `$link`, `$image`, `$title`, `$price`, `$heading` and `$priority`. An image, title or
price the card does not hold is not rendered. See [Overriding views](../customising/views.md).

### `<x-meilifacets::listing.card-template>`

The card written with binding attributes (`data-meili-text`, `data-meili-attr`, `data-meili-class`,
`data-meili-class-list`, `data-meili-if`) and no values, which the client copies for every hit. A card the server
renders with its values carries none of them. `<x-meilifacets::listing.results>` renders it inside its `<template>`. It
renders `listing/card.blade.php`: overriding the card overrides both.

| Attribute | Type | Default | Values |
| --- | --- | --- | --- |
| `heading` | `HeadingLevel` or `string` | `h3` | `h2` to `h6` |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | `<article>` | `url`, `image`, `title`, `price` |

### `<x-meilifacets::listing.facets>`

Every declared filter not placed on its own, in declared order, each through `listing.facet` or `listing.price`.
In `submit` mode it ends with `<x-meilifacets::listing.apply shape="block">`. Not rendered when nothing is left
and no Apply button is due.

| Attribute | Type | Default | Effect |
| --- | --- | --- | --- |
| `name` | `string` | `''` | passed to every child |
| `scroll` | `bool` | `false` | on the group |
| `collapsible` | `bool` | `false` | passed to every child |
| `with-apply` | `bool` | `true` | `false` drops the block Apply button of `submit` mode |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | nowhere | `facets`, plus those of each child; `data-apply` carries the apply mode |

### `<x-meilifacets::listing.facet>`

One term facet: a `<fieldset>` of checkboxes (several values) or radios (one value). Hidden while no value is
readable.

| Attribute | Type | Default | Values |
| --- | --- | --- | --- |
| `facet` | `string`, `BackedEnum` or `Facet` | required | the facet's `name`, or the enum case whose value is that name |
| `name` | `string` | `''` | |
| `scroll` | `bool` | `false` | |
| `presentation` | `string` or `ValuePresentation` | the facet's own | `control`, `pill`; a theme's own presentation must be bound (`:presentation`) |
| `collapsible` | `bool` | `false` | the legend becomes a toggle and the values a panel |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | `<fieldset>` | `facet`, `facet-value`, `input`, `count`, `more`, `more-label`, `less-label`; with `collapsible`: `toggle`, `selected-count`, `panel` |

Also renders `data-taxonomy`, and `data-presentation` when the presentation is not `control`.

### `<x-meilifacets::listing.price>`

The price filter: a slider, two fields, or both, as the `PriceFilter` declares. Hidden while the catalogue has no
price range.

| Attribute | Type | Default | Values |
| --- | --- | --- | --- |
| `facet` | `string`, `BackedEnum` or `PriceFilter` | required | the filter's `name` (`price` unless declared otherwise) |
| `name` | `string` | `''` | |
| `scroll` | `bool` | `false` | |
| `collapsible` | `bool` | `false` | |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | `<fieldset>` | `facet`; through its sub-views `price-range`, `price-track`, `price-handle`, `price-tip`, `price-readout`, `price-bounds-min`, `price-bounds-max`, `price-min`, `price-max`; with `collapsible`: `toggle`, `selected-count`, `panel` |

Also renders `data-filter` with the filter's name. Sub-views: `listing.price.range` when the filter shows
`PricePart::Slider`; `listing.price.fields` when it shows `PricePart::Fields`, `listing.price.hidden` otherwise.

### `<x-meilifacets::listing.sort>`

The sort control. Not rendered when one order or none is offered. Rendered once per page per listing.

| Attribute | Type | Default | Values |
| --- | --- | --- | --- |
| `name` | `string` | `''` | |
| `scroll` | `bool` | `false` | |
| `widget` | `SortWidget` or `string` | `listbox` | `listbox` (button and list), `radios` (a fieldset of radios) |
| `collapsible` | `bool` | `false` | `radios` only; ignored by `listbox` |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | nowhere | `listbox`: `sort`, `sort-trigger`, `sort-list`, `sort-option`. `radios`: `sort-choices`, `sort-choice-row`, `sort-choice`; with `collapsible`: `toggle`, `sort-chosen`, `panel` |

### `<x-meilifacets::listing.pagination>`

Previous, a window of page buttons, next. Rendered hidden when there is a single page: the client fills these
slots, it never adds any.

| Attribute | Type | Default |
| --- | --- | --- |
| `name` | `string` | `''` |
| `scroll` | `bool` | `false` |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | nowhere | `pagination`, `previous`, `page`, `next` |

### `<x-meilifacets::listing.total>`

The result count in words, and a polite live region that announces it after each search.

| Attribute | Type | Default |
| --- | --- | --- |
| `name` | `string` | `''` |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | the first `<p>` | `total`, `total-status` |

### `<x-meilifacets::listing.active-filters>`

The number of active filters, in words. Hidden at zero.

| Attribute | Type | Default | Effect |
| --- | --- | --- | --- |
| `name` | `string` | `''` | |
| `scroll` | `bool` | `false` | accepted, but the view does not render `data-meili-scroll`: no effect |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | nowhere | `active-filters` |

### `<x-meilifacets::listing.active-values>`

One removable pill per active value: search term, term or price range. Hidden when nothing is active.

| Attribute | Type | Default |
| --- | --- | --- |
| `name` | `string` | `''` |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | `<ul>` | `active-values`, `active-value`, `active-value-template`; each pill carries `data-kind` (`search`, `term`, `price`) |

### `<x-meilifacets::listing.reset>`

“Clear all”. Hidden while nothing is active.

| Attribute | Type | Default | Values |
| --- | --- | --- | --- |
| `name` | `string` | `''` | |
| `scroll` | `bool` | `false` | |
| `shape` | `ResetShape` or `string` | `text` | `text`, `pill`, `icon` |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| `icon` (`icon` shape only: an empty slot shows no icon, no slot shows the module's `trash.svg`) | nowhere | `reset`; `data-shape` for `pill` and `icon` |

### `<x-meilifacets::listing.apply>`

The Apply button. Rendered in `submit` mode, where it runs the pending search. With `visible-in-drawer` it is
rendered in `immediate` mode too, where it only closes the drawer and is shown only while the drawer is a sheet.

| Attribute | Type | Default | Values |
| --- | --- | --- | --- |
| `name` | `string` | `''` | |
| `visible-in-drawer` | `bool` | `false` | |
| `shape` | `ApplyShape` or `string` | `pill` | `pill` (“Apply” and a count badge), `block` (“Apply filters”) |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | `<button>` | `apply`; `active-count` with `pill`; `data-shape="pill"`; `data-only="sheet"` in `immediate` mode |

### `<x-meilifacets::listing.search>`

A search field that filters the listing by text, inside a `<form role="search" method="get">` that keeps the
other parameters of the page. Not rendered on a WordPress search results page, where the listing already reads
the routed term.

| Attribute | Type | Default |
| --- | --- | --- |
| `name` | `string` | `''` |
| `label` | `?string` | `null`: “Search this list” |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | `<form>` | `listing-search`, `listing-search-input`, `listing-search-clear` |

The field is named after the `q` parameter (renamable, see [configuration](configuration.md#module-settings)) and
keeps at most 200 characters.

### `<x-meilifacets::listing.drawer>`

A container that becomes a modal bottom sheet while `media` matches, a modal side sheet from `48em` past `row-limit`
filters shown, and stays inline otherwise.

| Attribute | Type | Default | Values |
| --- | --- | --- | --- |
| `name` | `string` | `''` | |
| `media` | `string` | `(width < 48em)` | a media query; the stylesheet's own breakpoints do not follow it |
| `heading` | `HeadingLevel` or `string` | `h2` | `h2` to `h6`, for the “Filters” title |
| `row-limit` | `int` | `meilifacets.drawer.row_limit`, else `5` | the number of filters it holds and shows at page load past which it opens as a side sheet |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| default (the body), `footer` (its own attributes land on the footer `<div>`) | the drawer `<div>` | `drawer`, `drawer-sheet`, `drawer-title`, `drawer-close` (button and drag handle), `drawer-body`, `drawer-footer` (with the slot); `data-media`, `data-side-sheet` past the limit |

### `<x-meilifacets::listing.drawer-opener>`

The “Filters” button that opens the drawer, with a count badge.

| Attribute | Type | Default | Effect |
| --- | --- | --- | --- |
| `name` | `string` | `''` | |
| `scroll` | `bool` | `false` | accepted, but the view does not render `data-meili-scroll`: no effect |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| `icon` (an empty slot shows no icon, no slot shows the module's `filters.svg`) | `<button>` | `drawer-open`, `active-count` |

### `<x-meilifacets::listing.unavailable>`

The message shown in place of the listing when the engine failed on the server. Rendered by the module itself.

| Attributes | Slots | Bag lands on | Hooks rendered |
| --- | --- | --- | --- |
| none | none | nowhere | none (`role="alert"`) |

## Search components

### `<x-meilifacets::search>`

The site search root. It renders `data-search` and `data-meili-contract`, asks for the `meilifacets-site-search`
stylesheet and the `@meilifacets/site-search` script module. Empty, it renders the default composition: toggle,
panel, input, one section per searchable type, empty state, unavailable message. With content, it renders only
what you placed.

| Attribute | Type | Default | Values |
| --- | --- | --- | --- |
| `name` | `string` | `search` | unique on the page |
| `min-chars` | `?int` | `null`: `SearchSettings` (`2`) | characters typed before the first search |
| `delay` | `?int` | `null`: `SearchSettings` (`120`) | milliseconds of quiet typing before a search leaves |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| default (manual composition), `icon` (passed to the default toggle) | root `<div>` | `search` |

### `<x-meilifacets::search.toggle>`

The magnifier button that opens and closes the panel.

| Attribute | Type | Default |
| --- | --- | --- |
| `name` | `string` | `''` |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| `icon` (an empty slot shows no icon, no slot shows the module's `search.svg`) | `<button>` | `search-toggle` |

### `<x-meilifacets::search.panel>`

The panel, `role="search"`, hidden until opened. It appends the status live region after its slot.

| Attribute | Type | Default |
| --- | --- | --- |
| `name` | `string` | `''` |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| default | `<div role="search">` | `search-panel`, `search-status` |

### `<x-meilifacets::search.input>`

The search field, a combobox, inside a wrapper.

| Attribute | Type | Default |
| --- | --- | --- |
| `name` | `string` | `''` |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | `<input>` | `search-field` (wrapper), `search-input` |

### `<x-meilifacets::search.section>`

The results of one content type: heading with count, “See all” link when the type has an archive, result list
and card template. One section per type per root.

| Attribute | Type | Default | Values |
| --- | --- | --- | --- |
| `type` | `string` | required | a searchable post type, for example `product` |
| `limit` | `int` | `null`: `SearchSettings` (`4`) | a whole number, at least `1` |
| `name` | `string` | `''` | |

| Slots | Bag lands on | Hooks rendered |
| --- | --- | --- |
| none | section `<div>` | `search-section`, `search-count`, `search-see-all`, `search-results`, `search-card-template`, `card`; `data-type`, `data-limit` |

The card inside the template is the type's card component, `meilifacets::search.card` unless the type declares
another (see [Searchable types](../search/types.md)).

### `<x-meilifacets::search.card>`

The default card of every searchable type.

| Attributes | Slots | Bag lands on | Hooks rendered |
| --- | --- | --- | --- |
| none | none | `<a>` | `url` (on the `<a>`), `image`, `title`, `summary`, `price` |

### `<x-meilifacets::search.empty-state>`

“Nothing matches your search”, hidden until a search finds nothing.

| Attributes | Slots | Bag lands on | Hooks rendered |
| --- | --- | --- | --- |
| none | none | `<p>` | `search-empty` |

### `<x-meilifacets::search.unavailable>`

“Search unavailable”, hidden until the engine fails or the client cannot load.

| Attributes | Slots | Bag lands on | Hooks rendered |
| --- | --- | --- | --- |
| none | none | `<p>` | `search-unavailable` |

## Sub-views and variant views

Anonymous components receive only their `@props`. Variant views are rendered by a class component and read its
public properties: they are not tags of their own.

| View | Kind | Receives | Rendered by | Hooks rendered |
| --- | --- | --- | --- | --- |
| `components/listing/toggle.blade.php` | anonymous, `<x-meilifacets::listing.toggle>` | `disclosure` (`Disclosure`), default slot | `listing.facet`, `listing.price`, `listing.sort` with `collapsible` | `toggle`, `selected-count` |
| `components/listing/price/range.blade.php` | anonymous | `label`, `readout`, `fill`, `handles`, `bounds`, `money` | `listing.price` | `price-readout`, `price-range`, `price-track`, `price-handle`, `price-tip`, `price-bounds-min`, `price-bounds-max` |
| `components/listing/price/fields.blade.php` | anonymous | `handles`, `bounds`, `money` | `listing.price` | `price-min`, `price-max` |
| `components/listing/price/hidden.blade.php` | anonymous | `handles` | `listing.price` | `price-min`, `price-max` |
| `components/listing/reset-icon.blade.php` | variant view of `Listing\Reset` | the component | `listing.reset shape="icon"` | `reset` |
| `components/listing/sort-radios.blade.php` | variant view of `Listing\Sort` | the component | `listing.sort widget="radios"` | `sort-choices`, `sort-choice-row`, `sort-choice`, `sort-chosen`, `toggle`, `panel` |

## Watch out

- A component that drops its attribute bag (`listing.results`, `listing.facets`, `listing.sort`,
  `listing.pagination`, `listing.reset`, `listing.active-filters`) loses a `class` without a message. Wrap it, or
  override its view.
- `priority` on `listing.card` takes an `ImagePriority` case only: `priority="eager"` is refused by PHP.
- `collapsible` on a `listbox` sort is ignored without a message.
- A facet or the sort rendered twice on one page throws: place a facet on its own before
  `<x-meilifacets::listing.facets>`, which then shows what is left.

## See also

- [`data-meili` hooks](hooks.md)
- [Overriding views](../customising/views.md)
- [Errors and console messages](errors.md)
