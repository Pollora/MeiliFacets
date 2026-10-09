# CSS custom properties

Every `--meili-*` token the two stylesheets declare, with its default and the element it is declared on.

You want to…

- [override a token](#overriding-a-token);
- [look up a listing token](#listing-tokens-meilifacetscss);
- [look up a search token](#search-tokens-site-searchcss);
- [know which properties the client writes](#properties-written-by-the-client);
- [know which tokens change with the pointer or the width](#tokens-redeclared-in-media-queries).

```css
.site-main [data-listing] {
    --meili-tint: color-mix(in srgb, var(--brand) 10%, transparent);
    --meili-radius: 0;
    --meili-card: 18rem;
}

.site-header [data-meili="search"] {
    --meili-search-max: 80rem;
    --meili-scrim: color-mix(in srgb, black 35%, transparent);
}
```

## Overriding a token

- Listing tokens are declared on `[data-listing]`, the listing root. Search tokens are declared on
  `[data-meili="search"]`, the search root. A value set on `:root` or `body` never reaches them: the root
  declares its own. Set yours on the root itself.
- Use a selector that outranks the module's (`.site-main [data-listing]`). The module's stylesheet may be printed
  after yours: the root asks for it while it renders.
- Colours are mixed from `currentColor` by default, so they follow the text colour of the root's context.
- Durations and easings are also read by the client for the motion it runs in JavaScript (column “Read by the
  client”). Set them in CSS: the client reads the computed value.

Families: **colour**, **size**, **layer** (`z-index`), **motion** (durations, easings, busy state), **image**.

## Listing tokens (`meilifacets.css`)

Declared on `[data-listing]`.

| Token | Default | Family | Drives | Read by the client |
| --- | --- | --- | --- | --- |
| `--meili-edge` | `color-mix(in srgb, currentColor 25%, transparent)` | colour | borders of the search field, pills, opener, toggles, panels, sort trigger | |
| `--meili-rule` | `color-mix(in srgb, currentColor 12%, transparent)` | colour | separators between facets and sort options; the price track outside the range | |
| `--meili-tint` | `color-mix(in srgb, currentColor 8%, transparent)` | colour | light fills: active-filter badge, active-value pills, hovered toggle, clear button and radio | |
| `--meili-press` | `color-mix(in srgb, currentColor 14%, transparent)` | colour | pressed and current fills: current page, keyboard-active sort option, hovered pill, pressed buttons | |
| `--meili-muted` | `color-mix(in srgb, currentColor 60%, transparent)` | colour | secondary text: value counts, price bounds, currency symbol, dash | |
| `--meili-surface` | `Canvas` | colour | background of floating panels, the sort list and the drawer sheet; price handle border | |
| `--meili-scrim` | `color-mix(in srgb, CanvasText 50%, transparent)` | colour | the backdrop behind the drawer sheet | |
| `--meili-ease` | `cubic-bezier(0.23, 1, 0.32, 1)` | motion | default easing: hovers, presses, lists, panels, fades | yes |
| `--meili-ease-drawer` | `cubic-bezier(0.32, 0.72, 0, 1)` | motion | the drawer sheet sliding in and out | |
| `--meili-ease-resize` | `cubic-bezier(0.25, 1, 0.5, 1)` | motion | the sheet's height and the footer button's width changing | |
| `--meili-ease-content` | `cubic-bezier(0.26, 0.08, 0.25, 1)` | motion | a collapsible panel's content opening | yes |
| `--meili-duration-resize` | `270ms` | motion | sheet height and footer button width changes | |
| `--meili-duration-content` | `270ms` | motion | a collapsible panel opening in the sheet | yes |
| `--meili-duration-drawer-in` | `350ms` | motion | the sheet and its backdrop opening | |
| `--meili-duration-drawer-out` | `250ms` | motion | the sheet and its backdrop closing | |
| `--meili-duration-fade` | `150ms` | motion | short fades: busy results, a panel closing; badge and pill entrances | yes |
| `--meili-duration-panel-in` | `180ms` | motion | a floating panel opening, from `48em` | |
| `--meili-duration-panel-out` | `120ms` | motion | a floating panel closing, from `48em` | |
| `--meili-duration-hover` | `150ms` | motion | hover colour changes | |
| `--meili-duration-press` | `150ms` | motion | the press scale of buttons | |
| `--meili-duration-press-pill` | `120ms` | motion | the press scale of pill values | |
| `--meili-duration-chevron` | `160ms` | motion | the arrow of toggles and of the sort trigger turning | |
| `--meili-duration-list` | `160ms` | motion | the sort list opening and closing | |
| `--meili-duration-pop-in` | `200ms` | motion | the icon reset appearing in the drawer footer | |
| `--meili-duration-pop-out` | `150ms` | motion | the icon reset leaving the drawer footer | |
| `--meili-duration-busy-delay` | `150ms` | motion | wait before the results dim during a search | |
| `--meili-duration-settle` | `120ms` | motion | the results coming back to full opacity | |
| `--meili-busy-opacity` | `0.55` | motion | opacity of the results during a search | |
| `--meili-card` | `15em` | size | minimum card width in the result grid | |
| `--meili-ui` | `0.875rem` | size | font size of controls: opener, toggles, sort, “Show more” | |
| `--meili-control` | `3rem` | size | height of controls and pills | |
| `--meili-control-min` | `0px` | size | minimum touch target; `2.75rem` on a coarse pointer | |
| `--meili-field-font-min` | `1rem` | size | smallest font size of the search field on a coarse pointer (avoids zoom on iOS) | |
| `--meili-pill` | `999px` | size | radius of pill-shaped controls | |
| `--meili-radius` | `0.25em` | size | radius of toggles, panels, the sort list and option rows | |
| `--meili-line` | `1.5rem` | size | line height used to align toggles, close button and value rows | |
| `--meili-control-inline` | `1.5rem` | size | inline padding of pills, toggles, opener and pill-shaped buttons | |
| `--meili-bar-gap` | `0.5rem` | size | gap between items of the filter row and of the sheet | |
| `--meili-section-gap` | `1.5rem` | size | space between facet blocks and around the drawer head | |
| `--meili-section-step` | `1rem` | size | padding of panels and of the drawer footer | |
| `--meili-row-gap` | `0.5rem` | size | space between values | |
| `--meili-drawer-gutter` | `2rem` | size | inline padding of the drawer head, body and footer | |
| `--meili-drawer-block` | `2.5rem` | size | block padding of the drawer body | |
| `--meili-panel-min` | `18rem` | size | minimum width of a floating panel | |
| `--meili-panel-max` | `28rem` | size | maximum width of a floating panel | |
| `--meili-panel-price` | `20rem` | size | width of the floating panel holding the price slider | |
| `--meili-side-sheet-width` | `26rem` | size | width of the side sheet past the row limit, capped at the screen's | |
| `--meili-listing-search-min` | `12rem` | size | flex basis of the listing search field in the filter row | |
| `--meili-listing-search-width` | `18rem` | size | width of the listing search field in the filter row | |
| `--meili-layer` | `10` | layer | floating panels | |
| `--meili-layer-drawer` | `100` | layer | the drawer and its backdrop | |

### Internal

| Token | Declared | Default | Note |
| --- | --- | --- | --- |
| `--meili-scrim-shown` | `@property`, not inherited | `1` | Backdrop opacity during a drag, written by the client on the drawer. Do not set it. |

## Search tokens (`site-search.css`)

Declared on `[data-meili="search"]`.

| Token | Default | Family | Drives | Read by the client |
| --- | --- | --- | --- | --- |
| `--meili-edge` | `color-mix(in srgb, currentColor 25%, transparent)` | colour | the field's border | |
| `--meili-rule` | `color-mix(in srgb, currentColor 12%, transparent)` | colour | the panel's bottom border, the line above each result list | |
| `--meili-tint` | `color-mix(in srgb, currentColor 6%, transparent)` | colour | a hovered result | |
| `--meili-tint-active` | `color-mix(in srgb, currentColor 10%, transparent)` | colour | the keyboard's current result | |
| `--meili-halo` | `color-mix(in srgb, currentColor 8%, transparent)` | colour | the field's focus ring | |
| `--meili-muted` | `color-mix(in srgb, currentColor 65%, transparent)` | colour | summaries and prices; the unavailable message | |
| `--meili-surface` | `Canvas` | colour | background of the panel and of the sticky field | |
| `--meili-label` | `color-mix(in srgb, currentColor 70%, transparent)` | colour | section headings | |
| `--meili-glyph` | `color-mix(in srgb, currentColor 60%, transparent)` | colour | the magnifier and clear icons in the field | |
| `--meili-scrim` | `color-mix(in srgb, black 20%, transparent)` | colour | the veil over the page while the panel is open, from `48em` | |
| `--meili-ease` | `cubic-bezier(0.23, 1, 0.32, 1)` | motion | default easing: panel, toggle, veil | yes |
| `--meili-ease-fade` | `ease` | motion | crossfades between results | yes, only |
| `--meili-ease-exit` | `ease` | motion | the panel and the veil closing | |
| `--meili-ease-resize` | `var(--meili-ease)` | motion | the panel's height changing | yes, only |
| `--meili-duration-hover` | `150ms` | motion | hovered results, the “See all” arrow | |
| `--meili-duration-press` | `150ms` | motion | the toggle's press scale | |
| `--meili-duration-pop-in` | `200ms` | motion | the panel and the veil opening | |
| `--meili-duration-pop-out` | `150ms` | motion | the panel and the veil closing | |
| `--meili-duration-fade` | `150ms` | motion | results dimming and the busy bar during a search | yes |
| `--meili-duration-settle` | `120ms` | motion | results coming back to full opacity | yes |
| `--meili-duration-leave` | `120ms` | motion | a result leaving | yes, only |
| `--meili-duration-move` | `150ms` | motion | results moving to their new place | yes, only |
| `--meili-duration-resize` | `200ms` | motion | the panel's height changing | yes, only |
| `--meili-duration-bar` | `1.2s` | motion | one cycle of the busy bar | |
| `--meili-duration-busy-delay` | `150ms` | motion | wait before results dim and the bar shows | |
| `--meili-busy-opacity` | `0.55` | motion | opacity of results during a search | |
| `--meili-ui` | `0.875rem` | size | font size of the panel and of the field | |
| `--meili-small` | `0.8125rem` | size | “See all”, summaries and prices | |
| `--meili-control` | `3rem` | size | height of the field | |
| `--meili-control-min` | `0px` | size | minimum touch target of the toggle, field and “See all”; `2.75rem` on a coarse pointer | |
| `--meili-field-font-min` | `1rem` | size | smallest font size of the field on a coarse pointer | |
| `--meili-pill` | `999px` | size | radius of the field | |
| `--meili-radius` | `0.5rem` | size | radius of result rows, their focus ring and thumbnails | |
| `--meili-search-max` | `72rem` | size | maximum width of the panel's content | |
| `--meili-search-block` | `1.5rem` | size | block padding of the panel; offset of the sticky field | |
| `--meili-search-inline` | `1rem` | size | minimum inline padding of the panel | |
| `--meili-search-gap` | `2rem` | size | gap between sections; `3rem` from `48em` | |
| `--meili-search-lead` | `1.5rem` | size | space under the field when sections follow | |
| `--meili-search-column` | `18rem` | size | flex basis of a section column | |
| `--meili-search-thumb` | `3rem` | size | thumbnail size | |
| `--meili-search-row` | `0.75rem` | size | padding of result rows and of the field's wrapper | |
| `--meili-search-message` | `2rem` | size | block padding of the unavailable message | |
| `--meili-search-bar` | `2px` | size | thickness of the busy bar | |
| `--meili-search-glyph` | `1.125rem` | size | size of the magnifier and clear icons | |
| `--meili-search-glyph-inline` | `1rem` | size | inline offset of the magnifier in the field | |
| `--meili-layer-search` | `10` | layer | the panel and the veil | |
| `--meili-search-mark` | an inline SVG magnifier, as `url("data:image/svg+xml,…")` | image | mask of the magnifier in the field | |
| `--meili-search-clear` | an inline SVG cross, as `url("data:image/svg+xml,…")` | image | mask of the field's clear button | |

“yes, only”: the token is not used by the stylesheet, only by the client.

## Same name, different defaults

A token declared by both sheets keeps the value of the root it is read on. These differ:

| Token | Listing | Search |
| --- | --- | --- |
| `--meili-tint` | `currentColor 8%` | `currentColor 6%` |
| `--meili-muted` | `currentColor 60%` | `currentColor 65%` |
| `--meili-scrim` | `CanvasText 50%` | `black 20%` |
| `--meili-radius` | `0.25em` | `0.5rem` |
| `--meili-duration-resize` | `270ms` | `200ms` |
| `--meili-ease-resize` | `cubic-bezier(0.25, 1, 0.5, 1)` | `var(--meili-ease)` |

## Properties written by the client

Read-only: the client or the view writes them inline, and overwrites any value you set.

| Property | On | Meaning |
| --- | --- | --- |
| `--from` | `price-range` | position of the lower handle, `0` to `1` |
| `--to` | `price-range` | position of the upper handle, `0` to `1` |
| `--at` | each `price-handle` | position of the handle, `0` to `1` |
| `--meili-scrim-shown` | `drawer` | backdrop opacity while the sheet is dragged |
| `--meili-search-available-height` | `search-panel` | height left for the panel under its anchor |

## Tokens redeclared in media queries

| Token | Sheet | Condition | Value |
| --- | --- | --- | --- |
| `--meili-control-min` | both | `(pointer: coarse)` | `2.75rem` |
| `--meili-search-gap` | `site-search.css` | `(width >= 48em)` | `3rem` |

## Watch out

- The `48em` breakpoint is written in the media queries of both sheets. No token changes it.
- A token set on an ancestor shared by a listing and a search root does not reach either: both roots redeclare
  every token.

## See also

- [Styles and design tokens](../customising/styles.md)
- [Accessibility and motion](../accessibility.md)
- [`data-meili` hooks](hooks.md)
