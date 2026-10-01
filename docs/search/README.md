# Site search

This page covers `<x-meilifacets::search>`: a magnifier in the header that opens a panel of live results, one section
per content type.

You want to…

- [know what the search does](#what-it-does);
- [add it to a header in one line](#the-one-line-version);
- [anchor the panel under the right element](#where-the-panel-goes);
- [know which content types are searched, and in which order](#which-types-are-searched);
- [change how long the visitor types before a search leaves, or how many results a section shows](#settings);
- [put two searches on one page](#several-searches-on-one-page);
- [drop the module's stylesheet](#stylesheet).

## What it does

- A magnifier button (a disclosure) opens a panel under the header and puts the focus in the search field.
- The field is a combobox. Each pause in typing sends one request from the browser to Meilisearch, with one
  sub-query per section: no WordPress request is involved.
- The panel shows one section per content type: products, posts, pages, any custom post type that is indexed. Each
  section has its heading, a result count, its results and a « see all » link.
- The « see all » link points at the type's archive with the typed term in `?q=`. For products, it leads to the shop
  listing with the search already applied: `https://projet.ddev.site/shop/?q=creme`.
- ↓ and ↑ move through the results of every section, in the order the sections are placed. Enter follows the
  highlighted result. Escape closes the panel and gives the focus back to the magnifier.
- Matched words are highlighted in the title and the summary of each result.

The browser client is loaded the first time the visitor points at or focuses the magnifier or the field, not with the
page.

## The one-line version

```blade
<header class="site-header">
    {{-- logo, menu… --}}
    <x-meilifacets::search />
</header>
```

With no content, the root composes everything itself, in this order: the magnifier, the panel, the field, one section
per searchable type, the « nothing matches » message and the « search unavailable » message.

The magnifier shows the module's icon by default. Pass your own through the `icon` slot, or pass an empty slot to
show no icon at all:

```blade
<x-meilifacets::search>
    <x-slot:icon>
        <svg viewBox="0 0 24 24" width="20" height="20"><!-- … --></svg>
    </x-slot:icon>
</x-meilifacets::search>
```

The icon is decorative (`aria-hidden="true"`). The button's accessible name is its `aria-label`, « Search ».

To choose the sections, their order and their limits, or to place the field elsewhere, see
[Composing the search panel](composition.md).

## Where the panel goes

The panel is `position: absolute`, `top: 100%`, full width. It hangs under the **first positioned ancestor** of the
search root. The root and its bricks are never positioned, so the theme picks the anchor, usually the header bar:

```css
.site-header {
    position: relative;
}
```

| Viewport | Panel |
| --- | --- |
| below `48em` | fills the whole height left under the anchor; the page behind it does not scroll while it is open |
| from `48em` | as tall as its content, capped by the height left under the anchor; a scrim covers the page under the anchor, never the anchor itself |

The panel scrolls inside itself. A click on the scrim is a click outside the panel: it closes the panel and activates
nothing underneath.

While the panel is open, the root carries `data-open`. The module's stylesheet hangs the scrim and the scroll lock on
it, and a theme can do the same:

```css
.site-header:has([data-meili="search"][data-open]) {
    background: Canvas;
}
```

The available height is measured by the client when the panel opens and when the window is resized. It is written as
`--meili-search-available-height` on the panel.

## Which types are searched

By default, every post type MeiliScout indexes that is public and not `exclude_from_search`. With WooCommerce active,
products come first; the other types follow the order of MeiliScout's indexed post types setting. Headings and
« see all » labels are the post type's own labels (`name` and `all_items`), read from WordPress.

A post type without an archive (pages, for example) gets a section with no « see all » link.

To add, remove, rename or reorder types, see [Searchable content types](types.md).

## Settings

| Setting | Default | Set on one search | Meaning |
| --- | --- | --- | --- |
| characters before the first search | 2 | `min-chars` on `<x-meilifacets::search>` | a shorter term, or one with no letter or digit, sends nothing and clears the panel |
| delay | 120 | `delay` on `<x-meilifacets::search>` | milliseconds without a keystroke before the search leaves |
| results per section | 4 | `limit` on `<x-meilifacets::search.section>` | a whole number, at least 1 |

```blade
<x-meilifacets::search min-chars="3" delay="200" />
```

To change the defaults for the whole site, bind your own `SearchSettings` in the `register()` method of one of your
service providers. Named arguments change only what they name:

```php
use Modules\MeiliFacets\SiteSearch\SearchSettings;

public function register(): void
{
    $this->app->bind(
        SearchSettings::class,
        fn (): SearchSettings => new SearchSettings(minChars: 3, limit: 6),
    );
}
```

Use `bind` or `scoped`, never `bindIf`: the module binds its own default with `bindIf`, so a `bindIf` of yours is
ignored when the module registered first. An attribute on a component always wins over the bound default.

## Several searches on one page

A page can hold several roots, for example one in the desktop header and one in a mobile menu. Give each a `name`
(default: `search`):

```blade
<x-meilifacets::search name="header" />
<x-meilifacets::search name="drawer" />
```

Each root publishes its own description and renders its own element ids, so two roots with the same name are refused
when the page renders:

```text
A search named "search" is already rendered on this page: give each root its own name.
```

In a [manual composition](composition.md), once the page holds two roots, every brick needs the `name` of its root.

## Stylesheet

The module's stylesheet has the handle `meilifacets-site-search`. It is requested by the search root, so it loads only
on pages that render a search. When the root renders after `wp_head`, which is the case in a header, the stylesheet is
printed right before the root.

The stylesheet is neutral and mobile first, and it targets `data-meili` hooks, never classes. To restyle it, override
its custom properties: see [Styles and design tokens](../customising/styles.md). To drop it and write your own,
deregister the handle after the module has registered it:

```php
add_action('wp_enqueue_scripts', function (): void {
    wp_deregister_style('meilifacets-site-search');
}, 20);
```

`wp_dequeue_style()` is not enough: a root rendered after `wp_head` prints the handle directly.

## Watch out

- **The magnifier needs the browser connection and the published assets.** Without `browser.url` and `browser.key`
  in `config/meilifacets.php`, or without the client published by `php artisan module:publish MeiliFacets`, the page
  renders the magnifier but no script is loaded: the button does nothing and nothing says why. A URL without a scheme
  (`search.example.com` instead of `https://search.example.com`) counts as missing. See
  [Installation](../installation.md).
- **There is no results page.** Pressing Enter with no highlighted result does nothing, and the search does not fall
  back to WordPress's `?s=` form when JavaScript is off. Offer another way to search if you need one.
- **The client binds inside the root only.** A brick rendered outside `<x-meilifacets::search>` is ignored, and the
  browser console says which hooks to move.
- **An ancestor with `transform`, `filter` or `contain` becomes the anchor** of the panel, even without
  `position: relative`.
- If the client cannot be downloaded, the panel shows « Search unavailable ». If Meilisearch does not answer, the
  sections are hidden and the same message shows.

## See also

- [Composing the search panel](composition.md)
- [Searchable content types](types.md)
- [Search relevance](../indexing/relevance.md)
- [Styles and design tokens](../customising/styles.md)
- [Accessibility and motion](../accessibility.md)
- [Blade components](../reference/components.md)
