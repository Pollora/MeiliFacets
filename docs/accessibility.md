# Accessibility and motion

This page covers the accessibility patterns the module's components follow, what they announce to screen readers,
where they put the focus, and how they move, so you can keep all of it in an override.

You want to…

- [know which pattern each component follows](#patterns);
- [know the keyboard of each component](#keyboard);
- [know what is announced](#announcements);
- [know where the focus goes](#focus);
- [know what changes on touch screens](#touch);
- [know how motion is reduced](#motion);
- [know what forced-colours mode gets](#forced-colours);
- [know what to keep in an override](#what-to-keep-in-an-override).

## Patterns

### Listing

| Component | Pattern |
| --- | --- |
| Facet | a `<fieldset>` named by its `<legend>`; one native checkbox per value (a radio for a single-choice facet). Each box is named by the value's label (`aria-labelledby`) and described by its count (`aria-describedby`) |
| Facet count | written in words (« 14 results »), never a bare number. It describes the box rather than naming it: when it changes after a search, the name the screen reader is reading does not change under it |
| Pill values (`data-presentation="pill"`) | the same boxes and counts, visually hidden by the stylesheet but still read: the pill is the box's label |
| Value without results | hidden, unless its panel is open and the value is on screen: then it stays in place, marked `aria-disabled="true"` |
| « Show more » | a button with `aria-expanded`; both its labels are rendered, the client shows the one that applies |
| Collapsible section | the disclosure pattern: a `<button>` in the `<legend>`, with `aria-expanded` and `aria-controls` pointing at its panel. The number of values held is a badge that describes the button (`aria-describedby`) and is hidden from the reading order |
| Sort, default | a select-only combobox: a `<button role="combobox" aria-haspopup="listbox">` named by the « Sort by » label and its own text (« Sort by, Price, low to high »), and a `role="listbox"` of `role="option"` items with `aria-selected`. The stylesheet hides the label visually; it still names the button |
| Sort, `widget="radios"` | a `<fieldset>` of native radios |
| Price slider | two `<button role="slider">` handles, named « Lowest price » and « Highest price », with `aria-valuemin`, `aria-valuemax`, `aria-valuenow` and `aria-valuetext` (the amount with its currency) |
| Price fields | two `<input type="number">` labelled « From » and « To »; the currency symbol and the dash are hidden from screen readers |
| Pagination | a `<nav>` named « Pagination »; the page shown carries `aria-current="page"`. Pages are buttons, not links: nothing navigates |
| Active filters | a list named « Active filters »; each filter is a button named « Remove the … filter » |
| Clear all | a button; in its icon shape, named by `aria-label` |
| Listing search | a `<form role="search">` with a labelled `type="search"` field and a « Clear the search » button |
| Results | a list that carries `aria-busy="true"` while a search is out |
| Engine unavailable | a message with `role="alert"` in place of the listing |

### Drawer

Below `48em`, with JavaScript, the drawer opener turns the filters into a modal dialog:

- the opener is a button with `aria-expanded` and `aria-controls`; the count of held values describes it;
- open, the drawer takes `role="dialog"`, `aria-modal="true"` and `aria-labelledby` pointing at its title;
- everything around it, up to `<body>`, is made `inert`, and the page stops scrolling;
- the close button is named « Close the filters »; the drag handle is hidden from screen readers;
- crossing the breakpoint while it is open turns it back into ordinary content, without the dialog role.

From `48em`, or without JavaScript, the drawer is ordinary content: no dialog, nothing hidden.

### Site search

- The magnifier is a `<button>` named « Search », with `aria-expanded` and `aria-controls`. Its icon is hidden from
  screen readers.
- The panel is a `role="search"` region, shown and hidden as a **non-modal** disclosure: the page stays reachable.
- The field is an editable combobox: `<input type="search" role="combobox" aria-autocomplete="list">`. The client
  writes `aria-controls` (the result lists of the sections placed), `aria-expanded` (true while results are shown)
  and `aria-activedescendant` (the result the arrows reached).
- Each section is a heading and a `role="listbox"` named by that heading. Each result is a `role="option"` holding
  one link.
- The matched words are wrapped in `<mark>`. A result's thumbnail is decorative (`alt=""`): the link already reads
  the title. A listing card keeps its image's text alternative.

## Keyboard

| Component | Key | Effect |
| --- | --- | --- |
| Facet values, price fields, radios | native | as the browser's own controls |
| Collapsible section, floating | Escape | closes the panel and gives the focus back to its button |
| | Tab out of the panel | closes it |
| Sort, closed | ↓, ↑, Enter, Space | opens the list on the sort in use |
| | Home, End | opens the list on the first or last option |
| | a letter | opens the list on the first option starting with it |
| Sort, open | ↓, ↑, Home, End | moves through the options |
| | Enter, Space | picks the option and closes the list |
| | Tab | picks the option, then moves on |
| | Escape | closes the list |
| | letters | jump to the option whose name starts with them; letters typed within half a second spell one name |
| Price handle | ←, ↓ / →, ↑ | one currency unit down / up |
| | Shift + arrow | a tenth of the range (at least one unit) |
| | Home, End | the lowest / highest price |
| Drawer | Escape | closes it; a control inside that is open (the sort list) closes first |
| Site search field | ↓ | reaches the next result, across sections in the order they are placed |
| | ↑ | reaches the previous result; from the field, the last one |
| | Enter | follows the reached result's link; with no result reached, does nothing |
| | Home, End, ←, → | back to editing the text |
| | Escape | closes the panel and gives the focus back to the magnifier, without clearing the field |
| | Tab | moves from the field to the « See all » links: result links are out of the tab order |

A price handle moved by the keyboard searches once, when the key is released, not on every repeat. The site search
leaves keys alone while an input method is composing a character.

## Announcements

The module writes to three live regions, and to no other:

| Region | Says | When |
| --- | --- | --- |
| `total-status`, next to the listing total | the total in words, « 24 items » | after each search; while the visitor types in the listing search field, once typing has paused for a second |
| `search-status`, in the search panel | one sentence per section that found something (« Products: 3 results and Posts: 1 result »), or the empty or unavailable message | once a second has passed with no keystroke and no new answer |
| the engine unavailable message | « Search is temporarily unavailable… » | when the listing renders without the engine (`role="alert"`) |

- Both `polite` regions are visually hidden, and empty when the page loads, so nothing is read on arrival.
- A region never says the same sentence twice in a row.
- The visible total is not itself live: it follows every keystroke without talking over the visitor.
- Closing the search panel cancels the pending sentence and forgets the last one: reopened, the panel announces the
  same search again.
- Counts are always written in words, with the plural rules of the site's language.

## Focus

The module moves the focus only when the element holding it disappears, or when a dialog opens or closes. It never
leaves the focus on `<body>`.

| After | The focus goes to |
| --- | --- |
| opening the drawer | the drawer's title |
| closing the drawer | the button that opened it |
| opening the site search | the search field |
| Escape in the site search, or in a floating panel | the button that opened it |
| « Clear all », which then hides itself | the visible « Apply » in the same drawer, or in the listing; otherwise the drawer's title when the drawer is a sheet; otherwise the listing root |
| removing an active filter | the filter now at the same place in the list, otherwise the one before, otherwise the listing root |
| a page button that the new page hides | the button of the page shown |
| clearing the listing search | the search field |
| picking a sort option | the sort button |

When the focus falls back on the listing root, the client gives the root `tabindex="-1"` if it has no `tabindex`.

**Scrolling back to the listing.** A component placed with the `scroll` attribute
(`<x-meilifacets::listing.pagination scroll />`) scrolls the listing's top into view after a pointer activation. Not
after a keyboard one: the focus already says where the visitor is. The scroll sets no `behavior`, so your
`scroll-behavior`, and how it treats reduced motion, decides whether it is smooth.

## Touch

On a coarse pointer (`@media (pointer: coarse)`):

- every control, value row and button of both components is at least `--meili-control-min` high, `2.75rem` by
  default;
- value rows and sort options get more padding;
- the search fields' text is never smaller than `--meili-field-font-min` (`1rem`): iOS zooms into a field whose text
  is under 16 pixels.

## Motion

Durations and easings are tokens (see [Styles and design tokens](customising/styles.md#motion)). The client times the
animations it plays itself from the same tokens.

**Under `prefers-reduced-motion: reduce`, motion becomes fades.** Nothing slides, scales or travels:

- filter sections, floating panels and the sort list fade in and out, without moving;
- the drawer fades instead of sliding up; its height changes at once; dragging it no longer moves the sheet with the
  pointer, but a drag down still closes it;
- badges, active filters and search results that appear fade in, without scaling;
- search results do not glide to their new rank, and a leaving result does not move;
- the search panel's height changes at once, and its busy bar becomes a fade;
- pressed buttons do not shrink, chevrons and price handles do not animate;
- hover transitions of the search results are left out.

**The keyboard opens and closes without animation.** Escape closes the drawer at once. A floating panel or the
search panel opened or closed by the keyboard changes at once: the client sets `data-instant` for the time of the
change.

## Forced colours

Both stylesheets have a `@media (forced-colors: active)` block:

- the sort option and the search result reached by the keyboard are outlined;
- a checked pill value takes `SelectedItem` and `SelectedItemText`;
- the count badges take `CanvasText` and `Canvas`;
- the icons drawn in the search field take `CanvasText`.

## What to keep in an override

The client finds elements by their hooks, but assistive technologies need the rest of the markup. When you override
a view (see [Overriding views](customising/views.md)), keep:

- every `id`, `for`, `aria-labelledby`, `aria-describedby`, `aria-controls` and `aria-label` the module's view
  writes, and the elements they point at;
- the roles: `combobox`, `listbox`, `option`, `slider`, `search`, `alert`;
- `aria-expanded="false"`, `aria-selected` and `aria-current="page"` as rendered: the client updates them, it does
  not add them;
- `<fieldset>` and `<legend>` around a facet's values, or another grouping with an accessible name;
- the `total-status` and `search-status` regions, with `aria-live="polite"`: without them, results change in
  silence;
- `tabindex="-1"` on the drawer's title, which receives the focus;
- `aria-hidden="true"` on decorative icons, on the ✕ marks, and on the badges that describe a button;
- buttons as buttons: pagination, sort, apply and reset do not navigate, and a link there would announce a navigation
  that never happens.

## Watch out

- **Without JavaScript, the listing is readable but inert.** The page is complete as the server renders it, but
  facets, sort and pagination are buttons and boxes that do nothing. The listing search field is a real form and still
  works. The site search magnifier does nothing without its client.
- **Your own card is yours to make accessible.** The module's card is a link around the image and the title. A card
  with a second link (add to cart) needs a name that says which product it acts on.
- **A theme that sets `display` on a hooked element** can show what the client hid. Keep
  `[data-meili][hidden] { display: none !important; }` if you drop the stylesheet.
- **Contrast is yours.** Colours are mixes of the text colour: check the muted text (`--meili-muted`) and the
  disabled values against your background.

## See also

- [Overriding views](customising/views.md)
- [Styles and design tokens](customising/styles.md)
- [Mobile drawer and filter bar](listing/drawer.md)
- [Site search](search/README.md)
- [CSS custom properties](reference/css-tokens.md)
