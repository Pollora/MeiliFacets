# Mobile drawer and filter bar

This page covers the drawer: the filters in a bottom sheet on small screens, and the same filters in a row of pills
on larger ones.

You want to…

- [put the filters in a bottom sheet on mobile and in a row on desktop](#minimal-example);
- [know how the sheet behaves](#below-48em-a-bottom-sheet);
- [know how the row behaves](#from-48em-a-row-of-pills);
- [put Clear all and Apply in the drawer footer](#the-footer);
- [change the opener's icon](#the-opener);
- [change the breakpoint](#changing-the-breakpoint);
- [know what Apply does in `immediate` mode](#apply-in-immediate-mode).

## Minimal example

```blade
<x-meilifacets::listing>
    <div class="shop-filters">
        <x-meilifacets::listing.drawer-opener />
        <x-meilifacets::listing.drawer class="shop-filters__drawer">
            <x-meilifacets::listing.search />
            <x-meilifacets::listing.sort widget="radios" collapsible />
            <x-meilifacets::listing.facets collapsible :with-apply="false" />
            <x-slot:footer>
                <x-meilifacets::listing.reset shape="icon" />
                <x-meilifacets::listing.apply visible-in-drawer />
            </x-slot:footer>
        </x-meilifacets::listing.drawer>
        <x-meilifacets::listing.total class="shop-filters__total" />
    </div>

    <x-meilifacets::listing.results />
    <x-meilifacets::listing.pagination scroll />
</x-meilifacets::listing>
```

The pattern has four parts, each a component of its own:

- **the opener**, a “Filters” button shown on small screens only;
- **the drawer**, an ordinary container whose slot holds any listing component;
- **collapsible facets and sort**, which fold into accordion sections in the sheet and into pills in the row (see
  [Collapsible facets](facets.md#collapsible-facets));
- **the footer**, an optional slot for Clear all and Apply.

The layout is mobile first: the stylesheet describes the sheet below `48em` and the row from `48em`.

## Below 48em: a bottom sheet

With JavaScript, the drawer is hidden and the opener is shown. The opener turns the drawer into a modal bottom sheet:

- the sheet is a dialog (`role="dialog"`, `aria-modal="true"`, named by its title), the rest of the page is made
  `inert`, and the page behind stops scrolling;
- the focus moves to the sheet's title; on close, it returns to the opener;
- Escape, the ✕ button, a tap on the handle, a click on the scrim and Apply close the sheet. Escape first closes what a
  control inside holds open, such as the sort list;
- the sheet can be dragged down from its handle, its header, or a body scrolled to the top. It closes past a quarter
  of its height, or on a quick flick; otherwise it springs back. Fields, the price slider and buttons keep their own
  gestures. Under reduced motion, the sheet does not follow the finger, but the gesture still closes it;
- the height of the sheet follows its content as sections open and close;
- while the sheet covers the page, the results behind it are not repainted; the latest answer is painted when the
  sheet closes.

The opener carries the number of active filters in a badge, pending ones included, hidden at zero. The badge
describes the button without being part of its name.

## From 48em: a row of pills

The opener is hidden and the drawer is a plain row: its header and handle are hidden, its body and footer sit side by
side.

- Each collapsible facet, price filter or sort is a pill; its panel floats under it. One
  panel is open at a time, Escape and a click outside close it, and a panel that would overflow the viewport on the
  right is aligned to its pill's right edge.
- The search field takes between `--meili-listing-search-min` (12rem) and `--meili-listing-search-width` (18rem).
- The body shrinks before the footer wraps under it: Apply stays at the end of the row, aligned with the last line of
  pills when they wrap.

## Without JavaScript

The sheet styles only apply when scripting is enabled. Without JavaScript, nothing is hidden: the opener is not
shown and the filters stay in the page, in line. They do not filter without the client (see
[How a listing works](README.md#without-javascript)).

## The drawer

| Attribute | Default | Effect |
| --- | --- | --- |
| `heading` | `h2` | the level of the sheet's title, `h2` to `h6` |
| `media` | `(width < 48em)` | the media query under which the opener opens the sheet. See [Changing the breakpoint](#changing-the-breakpoint) |
| `name` | the only listing | the listing it belongs to |

The attribute bag lands on the drawer's container. The title reads “Filters”, translatable.

### The footer

The `footer` slot is rendered only when given. Its own attributes land on the footer element:

```blade
<x-slot:footer class="shop-filters__footer">
    <x-meilifacets::listing.reset shape="icon" />
    <x-meilifacets::listing.apply visible-in-drawer />
</x-slot:footer>
```

In the sheet, the footer stays under the scrolling body: Apply spans the width, and makes room on its left for an
icon-shaped Clear all while there is something to clear. In the row, the footer follows the pills.

Give the group `:with-apply="false"` when the footer holds an Apply, or the group renders a second one in `submit`
mode.

## The opener

`<x-meilifacets::listing.drawer-opener />` reads “Filters” with the filters icon. Its attribute bag lands on the
`<button>`.

The `icon` slot replaces the icon; an empty slot removes it:

```blade
<x-meilifacets::listing.drawer-opener>
    <x-slot:icon><svg viewBox="0 0 16 16" width="14" height="14">…</svg></x-slot:icon>
</x-meilifacets::listing.drawer-opener>

<x-meilifacets::listing.drawer-opener>
    <x-slot:icon></x-slot:icon>
</x-meilifacets::listing.drawer-opener>
```

The slot is hidden from screen readers. An icon that carries meaning of its own needs an override of
`components/listing/drawer-opener.blade.php`.

The default icons are served from `public/modules/meilifacets/images/`, copied there by
`php artisan module:publish MeiliFacets`.

## Apply in immediate mode

In `immediate` mode, every check searches already, so Apply has nothing to send:

- `<x-meilifacets::listing.apply visible-in-drawer />` is rendered, visible only while the drawer is a bottom sheet,
  where it closes the sheet. It is never seen in the desktop row;
- without `visible-in-drawer`, no Apply is rendered in `immediate` mode.

In `submit` mode, Apply is visible in both layouts. In the sheet it sends the pending changes and closes the sheet.
See [Results, sorting and pagination](results-sort-pagination.md#apply).

## Changing the breakpoint

The `48em` threshold is written in two places, and both must change together:

1. the `media` attribute of the drawer, which the client reads to decide when the opener opens a sheet;
2. the media queries of the module's stylesheet, which draw the sheet, the opener and the row. A media query cannot
   read a custom property, so the stylesheet has no variable for it.

```blade
<x-meilifacets::listing.drawer media="(width < 64em)">
```

Then redeclare, in the theme's stylesheet, the rules the module writes under `(scripting: enabled) and (width < 48em)`
and `(width >= 48em)`, at the new value. There is no other way: changing `media` alone gives a sheet drawn as a row,
or a row that opens as a sheet. See [Styles and design tokens](../customising/styles.md).

## Styling

The drawer reads the module's custom properties, set on `[data-listing]`, among which:

| Property | Default | Drives |
| --- | --- | --- |
| `--meili-bar-gap` | `0.5rem` | the gap between pills in the row |
| `--meili-drawer-gutter` | `2rem` | the side padding of the sheet |
| `--meili-drawer-block` | `2.5rem` | the top and bottom padding of the sheet's body |
| `--meili-surface` | `Canvas` | the background of the sheet and of floating panels |
| `--meili-scrim` | `CanvasText` at 50 % | the scrim behind the sheet |
| `--meili-layer-drawer` | `100` | the `z-index` of the sheet |
| `--meili-duration-drawer-in`, `--meili-duration-drawer-out` | `350ms`, `250ms` | the sheet's entrance and exit |
| `--meili-panel-min`, `--meili-panel-max` | `18rem`, `28rem` | the width of a floating panel |

The full list is in [CSS custom properties](../reference/css-tokens.md). Motion, reduced motion and focus handling are
in [Accessibility and motion](../accessibility.md).

## Watch out

- **An ancestor with `transform`, `filter`, `contain` or `container-type`** traps the sheet: `position: fixed` then
  attaches to that ancestor instead of the viewport, and the sheet opens inside its box. Keep the listing out of such
  an ancestor.
- **The client must start.** The stylesheet hides the drawer below `48em` whenever scripting is enabled, whether or
  not the client is loaded. If the browser connection is not configured, or the client is not published, the opener
  shows and opens nothing, and the filters are out of reach on small screens. See [Installation](../installation.md).
- **Change the breakpoint in both places**: the `media` attribute and the stylesheet.
- **One Apply per footer, none in the group**: pass `:with-apply="false"` to `listing.facets` when the footer holds
  one.

## See also

- [Facets](facets.md), [Results, sorting and pagination](results-sort-pagination.md),
  [How a listing works](README.md)
- [Styles and design tokens](../customising/styles.md), [Overriding views](../customising/views.md)
- [Accessibility and motion](../accessibility.md), [CSS custom properties](../reference/css-tokens.md)
