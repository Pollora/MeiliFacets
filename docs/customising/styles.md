# Styles and design tokens

This page covers the module's two stylesheets, the CSS custom properties a theme sets to adapt them, and how to style
or replace them.

You want to…

- [know which stylesheet loads where](#two-stylesheets);
- [set the tokens from your theme](#where-to-set-the-tokens);
- [find a token and its default](#the-tokens);
- [style a state or a variant](#styling-by-hooks-and-states);
- [drop a stylesheet and write your own](#dropping-a-stylesheet);
- [change the breakpoint](#the-breakpoint);
- [change the icons](#icons).

A theme that matches the module to its design system usually sets a handful of tokens:

```css
body [data-listing],
body [data-meili="search"] {
    --meili-radius: 4px;
    --meili-surface: #ffffff;
    --meili-control: 2.75rem;
    --meili-duration-hover: 100ms;
}
```

## Two stylesheets

| Stylesheet | WordPress handle | Requested by |
| --- | --- | --- |
| `css/meilifacets.css` | `meilifacets` | `<x-meilifacets::listing>` |
| `css/site-search.css` | `meilifacets-site-search` | `<x-meilifacets::search>` |

Both are published under `public/modules/meilifacets/` by `php artisan module:publish MeiliFacets`, registered on
every page, and printed only on a page that renders their component.

Where a stylesheet is printed depends on when its component renders:

- a component rendered **before** `wp_head` (a Blade layout renders its sections before its `<head>`) has its
  stylesheet printed in the `<head>`, among the other enqueued styles;
- a component rendered **after** `wp_head` (a site header, a PHP template that prints the head first) has its
  stylesheet printed in the body, just before the component, after every stylesheet of the `<head>`.

Two listings on a page print one stylesheet.

The stylesheets are neutral. Colours derive from `currentColor` and the system colours `Canvas` and `CanvasText`.
**Neither stylesheet sets a font**: every control inherits `font: inherit` from the page, and text sizes are relative
(`rem`, `em`). The listing stylesheet lays out the results grid but does not style the cards: their appearance is the
theme's.

## Where to set the tokens

The defaults are declared on the component roots: `[data-listing]` for the listing, `[data-meili="search"]` for the
site search. **A value set on `:root` or `body` never reaches them**, because the root redeclares it.

Set the tokens on the same roots, **with a more specific selector**: `body [data-listing]` and
`body [data-meili="search"]`. The site search is usually rendered in the header, after `wp_head`, so its stylesheet
prints after the theme's: a plain `[data-meili="search"]` rule in the theme loses to the module's. The listing
stylesheet may print before or after the theme's, depending on where the listing renders. The `body` prefix wins in
both cases, and keeps winning if the template changes.

```css
body [data-listing] {
    --meili-card: 18rem;
    --meili-panel-max: 32rem;
}
```

Some tokens get a second value inside a media query. A plain override replaces both:

- `--meili-control-min` is `0px`, and `2.75rem` on a coarse pointer, on both roots. To change the touch floor, set it
  inside `@media (pointer: coarse)`.
- `--meili-search-gap` is `2rem`, and `3rem` from `48em`. To keep two values, set it twice.

```css
@media (pointer: coarse) {
    body [data-listing],
    body [data-meili="search"] {
        --meili-control-min: 3rem;
    }
}
```

## The tokens

Colours are mixes of `currentColor`: they follow the text colour of the component, light or dark. The same token can
have a different default in each stylesheet; a value set on a common ancestor changes both.

### Colours

| Token | Listing default | Search default | Paints |
| --- | --- | --- | --- |
| `--meili-edge` | `currentColor` 25 % | `currentColor` 25 % | borders of fields, controls and panels |
| `--meili-rule` | `currentColor` 12 % | `currentColor` 12 % | separator lines, the price track |
| `--meili-tint` | `currentColor` 8 % | `currentColor` 6 % | hover backgrounds, the active filters |
| `--meili-press` | `currentColor` 14 % | — | pressed and selected backgrounds, current page, tap highlight |
| `--meili-tint-active` | — | `currentColor` 10 % | the result the keyboard reached |
| `--meili-halo` | — | `currentColor` 8 % | focus ring of the search field |
| `--meili-muted` | `currentColor` 60 % | `currentColor` 65 % | counts, price bounds, summaries, messages |
| `--meili-label` | — | `currentColor` 70 % | section headings |
| `--meili-glyph` | — | `currentColor` 60 % | magnifier and clear icons in the field |
| `--meili-surface` | `Canvas` | `Canvas` | what sits over the page: floating panels, sort list, drawer sheet, search panel |
| `--meili-scrim` | `CanvasText` 50 % | `black` 20 % | the veil behind the drawer or the search panel |

### Sizes and spacing

| Token | Listing default | Search default | Sets |
| --- | --- | --- | --- |
| `--meili-ui` | `0.875rem` | `0.875rem` | text size of the controls |
| `--meili-small` | — | `0.8125rem` | summary, price and « see all » in results |
| `--meili-control` | `3rem` | `3rem` | height of controls and fields |
| `--meili-control-min` | `0px`, `2.75rem` on a coarse pointer | same | minimum height of touch targets |
| `--meili-field-font-min` | `1rem` | `1rem` | smallest text size of a search field on a coarse pointer |
| `--meili-pill` | `999px` | `999px` | radius of pill-shaped controls |
| `--meili-radius` | `0.25em` | `0.5rem` | radius of controls, value rows, results and thumbnails |
| `--meili-line` | `1.5rem` | — | line height of a section heading and of a value |
| `--meili-control-inline` | `1.5rem` | — | inline padding of pill controls |
| `--meili-card` | `15em` | — | minimum width of a results column |
| `--meili-bar-gap` | `0.5rem` | — | gap between the controls of the filter bar |
| `--meili-section-gap` | `1.5rem` | — | gap between filter sections |
| `--meili-section-step` | `1rem` | — | space above a section's panel, and in the drawer footer |
| `--meili-row-gap` | `0.5rem` | — | gap between values |
| `--meili-drawer-gutter` | `2rem` | — | inline padding of the drawer |
| `--meili-drawer-block` | `2.5rem` | — | block padding of the drawer body |
| `--meili-panel-min` | `18rem` | — | minimum width of a floating panel |
| `--meili-panel-max` | `28rem` | — | maximum width of a floating panel |
| `--meili-panel-price` | `20rem` | — | width of the price panel |
| `--meili-listing-search-min` | `12rem` | — | minimum width of the listing search field |
| `--meili-listing-search-width` | `18rem` | — | width of the listing search field |
| `--meili-search-max` | — | `72rem` | width of the panel's content; its background stays full width |
| `--meili-search-block` | — | `1.5rem` | block padding of the panel |
| `--meili-search-inline` | — | `1rem` | smallest inline padding of the panel |
| `--meili-search-gap` | — | `2rem`, `3rem` from `48em` | gap between sections |
| `--meili-search-lead` | — | `1.5rem` | space from the field to the first section |
| `--meili-search-column` | — | `18rem` | base width of a section from `48em` |
| `--meili-search-thumb` | — | `3rem` | size of a result thumbnail |
| `--meili-search-row` | — | `0.75rem` | padding of a result row |
| `--meili-search-message` | — | `2rem` | block padding of the empty and unavailable messages |
| `--meili-search-bar` | — | `2px` | thickness of the busy bar |
| `--meili-search-glyph` | — | `1.125rem` | size of the magnifier in the field |
| `--meili-search-glyph-inline` | — | `1rem` | space around the magnifier in the field |

### Layers

| Token | Default | Sets |
| --- | --- | --- |
| `--meili-layer` | `10` | `z-index` of a floating filter panel |
| `--meili-layer-drawer` | `100` | `z-index` of the drawer |
| `--meili-layer-search` | `10` | `z-index` of the search panel; its veil sits one below |

### Motion

| Token | Listing default | Search default | Times |
| --- | --- | --- | --- |
| `--meili-ease` | `cubic-bezier(0.23, 1, 0.32, 1)` | same | entrances and most transitions |
| `--meili-ease-drawer` | `cubic-bezier(0.32, 0.72, 0, 1)` | — | the drawer sliding in and out |
| `--meili-ease-resize` | `cubic-bezier(0.25, 1, 0.5, 1)` | `var(--meili-ease)` | a height that follows its content |
| `--meili-ease-content` | `cubic-bezier(0.26, 0.08, 0.25, 1)` | — | a filter section opening and closing |
| `--meili-ease-fade` | — | `ease` | fades alone: a result, section or message appearing or leaving |
| `--meili-ease-exit` | — | `ease` | the panel and its veil leaving |
| `--meili-duration-hover` | `150ms` | `150ms` | hover transitions |
| `--meili-duration-press` | `150ms` | `150ms` | a button pressed |
| `--meili-duration-press-pill` | `120ms` | — | a pill value pressed |
| `--meili-duration-fade` | `150ms` | `150ms` | fades, and every transition under reduced motion |
| `--meili-duration-pop-in` | `200ms` | `200ms` | drawer footer controls, search panel and veil coming in |
| `--meili-duration-pop-out` | `150ms` | `150ms` | the same, going out |
| `--meili-duration-drawer-in` | `350ms` | — | the drawer opening |
| `--meili-duration-drawer-out` | `250ms` | — | the drawer closing |
| `--meili-duration-panel-in` | `180ms` | — | a floating panel opening |
| `--meili-duration-panel-out` | `120ms` | — | a floating panel closing |
| `--meili-duration-content` | `270ms` | — | a filter section opening or closing; the client overrides it, see below |
| `--meili-duration-resize` | `270ms` | `200ms` | the drawer sheet's or the search panel's height |
| `--meili-duration-chevron` | `160ms` | — | a toggle's chevron turning |
| `--meili-duration-list` | `160ms` | — | the sort list opening and closing |
| `--meili-duration-settle` | `120ms` | `120ms` | results coming back after a search |
| `--meili-duration-leave` | — | `120ms` | a result leaving |
| `--meili-duration-move` | — | `150ms` | a result sliding to its new rank |
| `--meili-duration-bar` | — | `1.2s` | one cycle of the busy bar |
| `--meili-duration-busy-delay` | `150ms` | `150ms` | wait before results dim during a search |
| `--meili-busy-opacity` | `0.55` | `0.55` | opacity of dimmed results |

The client reads some of these tokens to time the animations it plays itself; it accepts `ms` and `s`. One
exception: the client computes a filter section's duration from the height it adds (between 150 and 270 ms, a little
shorter on the way out) and writes it over `--meili-duration-content`. Setting that token does not change a section's
speed; `--meili-ease-content` still sets its easing.

### Icons

| Token | Default | Draws |
| --- | --- | --- |
| `--meili-search-mark` | an SVG magnifier, as a data URI | the magnifier in the search field |
| `--meili-search-clear` | an SVG cross, as a data URI | the clear button of the search field |

Both are used as masks: the shape comes from the image, the colour from `--meili-glyph`. Replace them with another
`url()`.

### Written by the client

Do not set these: the client writes them, and anything you set is overwritten.

| Token | Written on | Holds |
| --- | --- | --- |
| `--meili-search-available-height` | the search panel | the height left under its anchor, while the panel is open |
| `--from`, `--to` | the price range | where the selected range starts and ends, from 0 to 1 |
| `--at` | a price handle | where the handle stands, from 0 to 1 |
| `--meili-scrim-shown` | the drawer | how much of the veil shows during a drag |

## Styling by hooks and states

Style by hooks and by the attributes that state what a component shows. The module's classes
(`meilifacetsFacet`…) are there, but a theme override can drop them; hooks stay.

| Attribute | On | Means |
| --- | --- | --- |
| `data-meili="…"` | every part | which part this is |
| `data-presentation="pill"` | a facet | values shown as pills; absent for the default presentation |
| `data-shape="pill"`, `data-shape="icon"` | apply, reset | the button's shape; absent for the default shape |
| `data-only="sheet"` | apply | shown only in the drawer, as a sheet |
| `data-align-end` | a floating panel | hangs from its trigger's end, because it would overflow the viewport |
| `data-active` | a sort option, a search result | the option the keyboard reached |
| `data-open` | the search root | the panel is open |
| `data-instant` | a panel, the drawer, the search root | the change happening now must not animate |
| `data-closing` | the drawer | the drawer is playing its exit |
| `data-dragging` | the drawer | the sheet follows a pointer |
| `data-leaving` | a search result, section or message | the node is fading out, out of the flow |
| `aria-expanded="true"` | a toggle, the sort trigger, the drawer opener | its panel is open |
| `aria-busy="true"` | the results, the search panel | a search is out |
| `aria-current="page"` | a page button | the page shown |
| `aria-selected="true"` | a sort option | the sort in use |
| `aria-disabled="true"` | a facet input | a value without results, kept in place in an open panel |
| `aria-modal="true"` | the drawer | the drawer is open as a sheet |

```css
body [data-listing] [data-meili="facet"][data-presentation="pill"] [data-meili="facet-value"] label {
    border-radius: 0;
    text-transform: uppercase;
}

body [data-listing] [data-meili="page"][aria-current="page"] {
    background: var(--brand-accent);
    color: white;
}
```

## Dropping a stylesheet

A theme that wants none of the module's styles removes the handle:

```php
add_action('wp_enqueue_scripts', function (): void {
    wp_dequeue_style('meilifacets');
    wp_deregister_style('meilifacets');
}, 20);
```

`wp_dequeue_style()` alone covers a listing printed in the `<head>`. `wp_deregister_style()` also covers a component
rendered after `wp_head`, which would otherwise print the stylesheet in the body. Do the same with
`meilifacets-site-search` for the search.

Your stylesheet then takes over what the module's did, and some of it is behaviour, not decoration:

- `[data-meili][hidden] { display: none !important; }`: the client hides and reveals by `hidden`, and a theme rule
  that sets `display` would show what should be hidden;
- the drawer below `48em`: the sheet, its veil, the locked page scroll;
- the floating panels from `48em`: the client decides whether a panel floats by reading its computed `position`
  (`absolute` means floating), so the stylesheet sets the behaviour;
- the search panel's position under its anchor, and its veil.

Read the module's stylesheet before replacing it.

## The breakpoint

Every width query of both stylesheets uses `48em`: below it, the filters stack and open in a drawer; from it, they
sit in a bar with floating panels, and the search panel gets its veil. A media query cannot read a custom property, so
the breakpoint is not a token.

To use another breakpoint:

1. pass it to the drawer: `<x-meilifacets::listing.drawer media="(width < 64em)">`;
2. redeclare, at your value, every block of the stylesheets written under `(width >= 48em)` and
   `(scripting: enabled) and (width < 48em)`.

There is no lighter way.

## Icons

The default icons are images published under `public/modules/meilifacets/images/`: `search.svg`, `filters.svg` and
`trash.svg`. They are printed as `<img alt="">` and replaced through the components' `icon` slots (see
[Overriding views](views.md#slots-or-overrides)). The two icons inside the search field are the mask tokens above.

## Watch out

- **Publish the assets.** A stylesheet that is not published under `public/modules/meilifacets/` is not registered,
  and nothing is printed. Publish again after each module update, then clear any page cache.
- **`:root` does not work.** The roots redeclare every token: set them on the roots, with the `body` prefix.
- **A `transform`, `filter`, `contain` or `container-type` on an ancestor** of the listing traps the drawer, which
  is `position: fixed`, inside that ancestor's box. The same properties on the search panel's anchor change where the
  panel and its veil sit.
- **The search panel hangs from its first positioned ancestor.** Give your header `position: relative` (or any
  position) so the panel opens under it.
- **The stylesheets support `forced-colors`.** If you restyle a selected or active state with a background, check it
  in a forced-colours mode: backgrounds are dropped there.

## See also

- [CSS custom properties](../reference/css-tokens.md)
- [Overriding views](views.md)
- [Accessibility and motion](../accessibility.md)
- [Mobile drawer and filter bar](../listing/drawer.md)
- [Composing the search panel](../search/composition.md)
