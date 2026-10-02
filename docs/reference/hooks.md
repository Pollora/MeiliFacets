# `data-meili` hooks

The attributes the browser client binds to, which every overridden view must keep, and which the module's two
stylesheets select on.

You want to…

- [know what a hook is and what “required” means](#how-hooks-work);
- [check the version and scroll attributes](#version-and-scroll-attributes);
- [look up a listing hook](#listing-hooks);
- [look up a search hook](#search-hooks);
- [know what else the client reads besides hooks](#beyond-the-hooks);
- [know which attributes the client writes, to style states](#state-attributes-written-by-the-client);
- [bind a card field in an overridden card](#card-binding-attributes).

```blade
{{-- An overridden facet value keeps its hooks; the classes are yours. --}}
<li class="shop-filter__value" {{ $hook('facet-value') }}>
    <label>
        <input type="{{ $inputType() }}" name="{{ $inputName() }}" value="{{ $value->slug }}" {{ $hook('input') }}>
        {{ $value->label }}
    </label>
</li>
```

In a class component's view, `$hook('name')` prints `data-meili="name"`. In an anonymous view, use the enum:
`{{ Modules\MeiliFacets\Enums\Hook::PriceTrack->attribute() }}`.

## How hooks work

- A hook is a `data-meili="…"` attribute. Its values are the cases of `Modules\MeiliFacets\Enums\Hook`. Classes
  belong to the theme and are never read by the client.
- The client binds per root: inside `[data-listing]` for listing hooks, inside `[data-search]` for search hooks.
  A hook outside every root is reported in the console and ignored.
- **Required** hooks are checked when the client starts. A rule applies to the root, or inside each element
  carrying its host hook; a host the theme did not render is not a breach. One breach and the client does not
  start for that root: the page stays as the server rendered it, and the console names what is missing.
- **Read when present** hooks are used if found, and skipped if not.
- **Not read** hooks exist for the stylesheets and for overrides; the client never looks them up.
- Every hook is also a CSS selector. `meilifacets.css` selects listing hooks (`[data-meili="facet"]`…);
  `site-search.css` selects search hooks, and the card hooks `card`, `url`, `image`, `title`, `price` and
  `summary` inside `search-results`. Both sheets hide any hooked element that has `hidden`
  (`[data-meili][hidden] { display: none !important }`). Column “Styled” says which sheet selects the hook.

## Version and scroll attributes

| Attribute | Rendered on | Value | Meaning |
| --- | --- | --- | --- |
| `data-meili-contract` | both roots | `1` | Version of the hook list. Raised when a hook is renamed or removed, never when one is added. The client refuses a root whose version differs. |
| `data-listing` | `<x-meilifacets::listing>` root | the listing name | Marks a listing root. |
| `data-search` | `<x-meilifacets::search>` root | the search name | Marks a search root. |
| `data-meili-scroll` | a component given `scroll` | none | A pointer click on a control inside it scrolls the listing root into view. Keyboard use never scrolls. Not part of the version: its absence is the default. |

## Listing hooks

Owned by `<x-meilifacets::listing>`. “Styled”: `listing` = `meilifacets.css`, `search` = `site-search.css`.

| Hook | Rendered on | Requirement | Client use | Styled | Explained in |
| --- | --- | --- | --- | --- | --- |
| `results` | `listing.results`, `<ul>` | required on the root | cards are written into it; `aria-busy` while searching | listing | [Results](../listing/results-sort-pagination.md) |
| `card-template` | `listing.results`, `<template>` | required on the root | its first element is copied for every hit | — | [Results](../listing/results-sort-pagination.md) |
| `empty` | `listing.results`, `<p>` | required on the root | shown when no card is shown | — | [Results](../listing/results-sort-pagination.md) |
| `no-results` | `<span>` inside `empty` | read when present, with `past-the-end` | shown when nothing matches | — | [Results](../listing/results-sort-pagination.md) |
| `past-the-end` | `<span>` inside `empty` | read when present, with `no-results` | shown when the page asked is past the last one | — | [Results](../listing/results-sort-pagination.md) |
| `card` | `<li>` around each listing card; `<li role="option">` around each search card | none | not read | search | [Results](../listing/results-sort-pagination.md) |
| `url` | the `<a>` of `listing.card` and `search.card` | required inside `search-card-template` | search: followed on Enter, taken out of the tab order; listing: not read | search | [Searchable types](../search/types.md) |
| `image` | the `<img>` of a card | none | not read | search | [Results](../listing/results-sort-pagination.md) |
| `title` | the title of a card | read when present | search: highlighted terms written as `<mark>` | search | [Searchable types](../search/types.md) |
| `price` | the price of a card | read when present | the card's `price` written as HTML, the element removed when empty | search | [Results](../listing/results-sort-pagination.md) |
| `summary` | the summary of a search card | read when present | highlighted terms written as `<mark>` | search | [Searchable types](../search/types.md) |
| `facets` | `listing.facets`, `<div>` | none | not read | listing | [Facets](../listing/facets.md) |
| `facet` | `listing.facet` and `listing.price`, `<fieldset>` | host of two rules | groups values, badges and price inputs | listing | [Facets](../listing/facets.md) |
| `facet-value` | one `<li>` per value | host of a rule | value shown, hidden or disabled | listing | [Facets](../listing/facets.md) |
| `input` | the checkbox or radio of a value | required inside `facet-value` | read and checked | listing | [Facets](../listing/facets.md) |
| `count` | the count of a value | read when present | rewritten after each search | listing | [Facets](../listing/facets.md) |
| `more` | “Show more” `<button>` | required inside a `facet` that holds `facet-value` | folds and unfolds values | listing | [Facets](../listing/facets.md) |
| `more-label` | `<span>` inside `more` | read when present | shown while folded | — | [Facets](../listing/facets.md) |
| `less-label` | `<span>` inside `more` | read when present | shown while unfolded | — | [Facets](../listing/facets.md) |
| `apply` | `listing.apply`, `<button>` | read when present | applies the pending state; closes the drawer | listing | [Apply](../listing/results-sort-pagination.md) |
| `toggle` | `listing.toggle`, `<button>` | read when present | opens the panel named by its `aria-controls` | listing | [Facets](../listing/facets.md) |
| `panel` | the values `<div>` of a collapsible facet or sort | required inside a `facet` or `sort-choices` that holds `toggle` | found through the toggle's `aria-controls`; the hook serves the rule and the sheet | listing | [Mobile drawer](../listing/drawer.md) |
| `selected-count` | badge inside `toggle` | read when present | number of selected values | listing | [Facets](../listing/facets.md) |
| `pagination` | `listing.pagination`, `<nav>` | host of a rule | shown or hidden | listing | [Pagination](../listing/results-sort-pagination.md) |
| `page` | page `<button>` | required inside `pagination` | its `value` is the page; `aria-current` written | listing | [Pagination](../listing/results-sort-pagination.md) |
| `previous` | `<button>` | required inside `pagination` | its `value` is the page | listing | [Pagination](../listing/results-sort-pagination.md) |
| `next` | `<button>` | required inside `pagination` | its `value` is the page | listing | [Pagination](../listing/results-sort-pagination.md) |
| `sort` | `listing.sort` listbox, `<div>` | host of a rule | binds the combobox | listing | [Sorting](../listing/results-sort-pagination.md) |
| `sort-trigger` | `<button role="combobox">` | required inside `sort` | opens the list; `aria-expanded` | listing | [Sorting](../listing/results-sort-pagination.md) |
| `sort-list` | `<ul role="listbox">` | required inside `sort` | shown and hidden | listing | [Sorting](../listing/results-sort-pagination.md) |
| `sort-option` | `<li role="option" data-value>` | required inside `sort` | `aria-selected`, `data-active` | listing | [Sorting](../listing/results-sort-pagination.md) |
| `sort-choices` | `listing.sort widget="radios"`, `<fieldset>` | host of two rules | not read directly | listing | [Sorting](../listing/results-sort-pagination.md) |
| `sort-choice` | radio `<input data-label>` | required inside `sort-choices` | read and checked | listing | [Sorting](../listing/results-sort-pagination.md) |
| `sort-choice-row` | `<li>` of a radio | read when present | shown or hidden | — | [Sorting](../listing/results-sort-pagination.md) |
| `sort-chosen` | `<span>` in the collapsible caption | read when present | the chosen sort's label | listing | [Sorting](../listing/results-sort-pagination.md) |
| `price-range` | slider `<div>` | host of a rule | `--from` and `--to` written | listing | [Price filter](../listing/price.md) |
| `price-track` | track `<div>` | required inside `price-range` | pointer drag | listing | [Price filter](../listing/price.md) |
| `price-handle` | `<button role="slider" data-bound>` | required inside `price-range` | `--at`, `aria-value*`, `data-active` while dragged | listing | [Price filter](../listing/price.md) |
| `price-tip` | `<span>` inside a handle | read when present | the handle's amount | listing | [Price filter](../listing/price.md) |
| `price-readout` | `<span>` | read when present | “min – max” | listing | [Price filter](../listing/price.md) |
| `price-bounds-min` | `<span>` | read when present | lowest price of the catalogue | listing | [Price filter](../listing/price.md) |
| `price-bounds-max` | `<span>` | read when present | highest price of the catalogue | listing | [Price filter](../listing/price.md) |
| `price-min` | number or hidden `<input>` | read when present | lower bound, read and written | listing | [Price filter](../listing/price.md) |
| `price-max` | number or hidden `<input>` | read when present | upper bound, read and written | listing | [Price filter](../listing/price.md) |
| `listing-search` | `listing.search`, `<form>` | host of a rule | submission intercepted | listing | [How a listing works](../listing/README.md) |
| `listing-search-input` | `<input type="search">` | required inside `listing-search` | the typed term | listing | [How a listing works](../listing/README.md) |
| `listing-search-clear` | `<button>` | read when present | clears the term | listing | [How a listing works](../listing/README.md) |
| `reset` | `listing.reset`, `<button>` | read when present | clears every filter; hidden when nothing is active | listing | [Reset](../listing/results-sort-pagination.md) |
| `active-filters` | `listing.active-filters`, `<span>` | read when present | count in words; hidden at zero | listing | [Active filters](../listing/results-sort-pagination.md) |
| `active-count` | badge in `listing.apply` and `listing.drawer-opener` | read when present | number of active filters | listing | [Mobile drawer](../listing/drawer.md) |
| `total` | `listing.total`, `<p>` | read when present | result count in words | listing | [Total](../listing/results-sort-pagination.md) |
| `total-status` | `<p aria-live="polite">` | read when present | announces the count | listing | [Total](../listing/results-sort-pagination.md) |
| `active-values` | `listing.active-values`, `<ul>` | host of a rule | pills rewritten | listing | [Active filters](../listing/results-sort-pagination.md) |
| `active-value-template` | `<template>` | required inside `active-values`; host of a rule | its first element is copied per pill | — | [Active filters](../listing/results-sort-pagination.md) |
| `active-value` | pill `<button data-kind>` | required inside `active-value-template` | removes its value | listing | [Active filters](../listing/results-sort-pagination.md) |
| `drawer` | `listing.drawer`, `<div data-media>` | host of a rule | modal sheet while `data-media` matches | listing | [Mobile drawer](../listing/drawer.md) |
| `drawer-title` | heading | required inside `drawer` | focused on open | listing | [Mobile drawer](../listing/drawer.md) |
| `drawer-close` | close `<button>` and drag handle | required inside `drawer` | closes; the handle is dragged | listing | [Mobile drawer](../listing/drawer.md) |
| `drawer-open` | `listing.drawer-opener`, `<button>` | read when present | opens; `aria-expanded` | listing | [Mobile drawer](../listing/drawer.md) |
| `drawer-sheet` | sheet `<div>` | read when present | slides and is dragged | listing | [Mobile drawer](../listing/drawer.md) |
| `drawer-body` | body `<div>` | none | not read | listing | [Mobile drawer](../listing/drawer.md) |
| `drawer-footer` | footer `<div>` | none | not read | listing | [Mobile drawer](../listing/drawer.md) |

## Search hooks

Owned by `<x-meilifacets::search>`: every hook named `search` or starting with `search-`. A search card also
carries `card`, `url`, `image`, `title`, `summary` and `price`, listed above.

| Hook | Rendered on | Requirement | Client use | Styled | Explained in |
| --- | --- | --- | --- | --- | --- |
| `search` | the search root `<div>` | none | not read (the root is found by `data-search`) | search | [Site search](../search/README.md) |
| `search-toggle` | `search.toggle`, `<button>` | read when present | opens and closes the panel; `aria-expanded` | search | [Composing the panel](../search/composition.md) |
| `search-panel` | `search.panel`, `<div role="search">` | required on the root | shown and hidden; `aria-busy` while searching | search | [Composing the panel](../search/composition.md) |
| `search-field` | wrapper of the input | none | not read | search | [Composing the panel](../search/composition.md) |
| `search-input` | `<input role="combobox">` | required on the root | the typed term; `aria-expanded`, `aria-activedescendant` | search | [Composing the panel](../search/composition.md) |
| `search-status` | `<p aria-live="polite">` | required on the root | announces results | search | [Composing the panel](../search/composition.md) |
| `search-empty` | `search.empty-state`, `<p>` | required on the root | shown when nothing matches | search | [Composing the panel](../search/composition.md) |
| `search-unavailable` | `search.unavailable`, `<p>` | required on the root | shown when the engine or the client fails | search | [Composing the panel](../search/composition.md) |
| `search-section` | `search.section`, `<div data-type data-limit>` | host of a rule | one per type | search | [Composing the panel](../search/composition.md) |
| `search-count` | `<span>` in the heading | required inside `search-section` | count in words | search | [Composing the panel](../search/composition.md) |
| `search-results` | `<ul role="listbox">` | required inside `search-section` | cards written into it | search | [Composing the panel](../search/composition.md) |
| `search-card-template` | `<template>` | required inside `search-section`; host of a rule | its first element is copied per hit | — | [Searchable types](../search/types.md) |
| `search-see-all` | “See all” `<a>` | read when present | its `href` carries the typed term | search | [Searchable types](../search/types.md) |

## Beyond the hooks

The client also relies on these, which an override must keep.

| Requirement | Where | Why |
| --- | --- | --- |
| `data-listing` or `data-search`, and `data-meili-contract="1"`, on the root | the root views | without them the root is not found, or refused |
| A `<template>` element | `card-template`, `active-value-template`, `search-card-template` | the client copies `template.content`; another element is skipped |
| A `<form>` holding an `<input>` | `listing-search`, `listing-search-input` | another element is skipped |
| `name` and `value` on a facet `<input>` | `facet.blade.php` | the name is the URL parameter, the value the term slug |
| `value` on `page`, `previous` and `next` `<button>`s | `pagination.blade.php` | the page number is read from it |
| `data-value` on `sort-option`, `value` and `data-label` on `sort-choice` | the sort views | the sort key and the label shown |
| `name` on `price-min` and `price-max` | the price sub-views | the URL parameter of each bound |
| `aria-controls` on `toggle`, `sort-trigger`, `drawer-open`, `search-toggle` | the views | names the element they open |
| `data-type` on `search-section` | `section.blade.php` | must name a type the search describes |
| `data-bound` on `price-handle` | `price/range.blade.php` | `min` or `max` |
| `data-media` on `drawer` | `drawer.blade.php` | the media query under which it is a sheet |

## Styling attributes rendered by the views

| Attribute | On | Values | Read by the client |
| --- | --- | --- | --- |
| `data-presentation` | `facet` | the presentation's slug, absent for `control` | no |
| `data-shape` | `apply`, `reset` | `pill` on both, `icon` on `reset`; absent for the default shapes | no |
| `data-only` | `apply` | `sheet`, in `immediate` mode | no |
| `data-apply` | `facets` | `submit`, `immediate` | no |
| `data-taxonomy` | `facet` (term facet) | the taxonomy | no |
| `data-filter` | `facet` (price) | the filter's name | no |
| `data-kind` | `active-value` | `search`, `term`, `price` | yes |
| `data-bound` | `price-handle` | `min`, `max` | yes |
| `data-media` | `drawer` | a media query | yes |
| `data-type`, `data-limit` | `search-section` | post type, number of results | yes |

## State attributes written by the client

| Attribute | On | Meaning |
| --- | --- | --- |
| `data-open` | search root | the panel is open (the sheet draws its scrim from it) |
| `data-instant` | search root, `drawer` | the next change happens without transition |
| `data-active` | `sort-option`, search result rows, `price-handle` | the keyboard's current option, or the handle being dragged |
| `data-closing` | `drawer` | the sheet is closing |
| `data-dragging` | `drawer` | the sheet follows the pointer |
| `data-leaving` | search sections and rows | a node fading out, no longer counted |
| `data-align-end` | `panel` | a floating panel aligned to the end of its toggle |
| `aria-busy` | `results`, `search-panel` | a search is running |
| `aria-expanded` | `toggle`, `more`, `sort-trigger`, `drawer-open`, `search-toggle`, `search-input` | open or closed |
| `aria-current` | `page` | the current page |
| `aria-selected` | `sort-option` | the current sort |
| `aria-modal` | `drawer` | the drawer is a modal sheet |
| `aria-disabled` | `input` of a facet value | the value would return no result |
| `aria-activedescendant` | `search-input` | the highlighted result |

## Card binding attributes

The card template fills its fields from the `card` field of each hit. These attributes are written by `CardBinding`
(`$binding` in the card view) and read by the client; the `data-meili` prefix is reserved for them.

| Attribute | Value | Effect |
| --- | --- | --- |
| `data-meili-text` | a field name | the element's text becomes the field; removed when empty |
| `data-meili-attr` | `attribute:field` pairs, space-separated; `alt:image_alt\|title` takes the first field holding a value | sets each attribute from its field |
| `data-meili-class` | `class:field` pairs | toggles each class on the field's truth |
| `data-meili-class-list` | a field name | adds the classes the field holds, space-separated |
| `data-meili-if` | `field` or `!field` | keeps the element only when the field is true (or false) |

Attributes that can be bound: `href`, `src`, `srcset`, `sizes`, `alt`, `title`, `width`, `height`, `value`,
`datetime`, `aria-*` and `data-*` (except `data-meili*`). A field name is letters, digits and underscores.

## Watch out

- Move the hook with the element when you wrap a component: a hook left on a wrapper the client does not expect
  breaks the rules above.
- A theme stylesheet that selects on classes survives an override only if the override keeps them. The module's
  sheets select on hooks, plus a few classes: `meilifacetsDrawerHandle`, `meilifacetsDrawerHead`,
  `meilifacetsDrawerIcon`, `meilifacetsFacetToggleLabel`, `meilifacetsHidden`, `meilifacetsPriceDash`,
  `meilifacetsPriceField`, `meilifacetsPriceFields`, `meilifacetsPriceInput`, `meilifacetsRangeBounds`,
  `meilifacetsRangeHead`, `meilifacetsResetIcon`.

## See also

- [Overriding views](../customising/views.md)
- [Styles and design tokens](../customising/styles.md)
- [Blade components](components.md)
- [Errors and console messages](errors.md#browser-console)
