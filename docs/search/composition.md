# Composing the search panel

This page covers how to build the search panel yourself from its bricks: which sections appear, in which order, with
which limits, and where the field, the messages and the magnifier go.

You want to…

- [choose the sections, their order and their limits](#manual-composition);
- [know what each brick renders](#the-bricks);
- [put the field, the messages or the magnifier somewhere else](#free-layout);
- [add a placeholder or a class to the field](#the-bricks);
- [keep a product section on a site that may run without WooCommerce](#guarding-a-product-section);
- [understand an error raised while the page renders](#rules-enforced-at-render);
- [change the markup of a brick, or the result card for the whole site](#overriding-a-bricks-view).

## Manual composition

As soon as `<x-meilifacets::search>` has content, it renders that content and nothing else. **Only the sections you
place are searched.**

```blade
<x-meilifacets::search min-chars="3">
    <x-meilifacets::search.toggle>
        <x-slot:icon>@include('partials.icons.search')</x-slot:icon>
    </x-meilifacets::search.toggle>

    <x-meilifacets::search.panel>
        <x-meilifacets::search.input placeholder="Search the site" />
        <x-meilifacets::search.section type="product" limit="6" />
        <x-meilifacets::search.section type="post" limit="3" />
        <x-meilifacets::search.empty-state />
        <x-meilifacets::search.unavailable />
    </x-meilifacets::search.panel>
</x-meilifacets::search>
```

This panel searches products and posts only, products first, even if pages are indexed too.

## The bricks

Every brick accepts an attribute bag: `class` and other HTML attributes land on its outer element, except on
`search.input`, where they land on the `<input>` itself.

| Brick | Attributes | Renders |
| --- | --- | --- |
| `<x-meilifacets::search.toggle>` | `name`, slot `icon` | the magnifier: a `<button>` with `aria-expanded` and `aria-controls` pointing at the panel |
| `<x-meilifacets::search.panel>` | `name` | a `<div role="search" hidden>` holding its slot, then a visually hidden live region that announces the results |
| `<x-meilifacets::search.input>` | `name`; the attribute bag goes on the `<input>` | a wrapper that carries the field's magnifier and stays stuck at the top of the scrolling panel, around an `<input type="search" role="combobox">` |
| `<x-meilifacets::search.section>` | `type` (required), `limit`, `name` | the type's heading and count, the « see all » link to its archive (absent when the type has none), a `role="listbox"` list named by the heading, and the `<template>` of its result card |
| `<x-meilifacets::search.empty-state>` | — | « Nothing matches your search », shown when no section found anything |
| `<x-meilifacets::search.unavailable>` | — | « Search unavailable », shown when the engine or the client cannot be reached |
| `<x-meilifacets::search.card>` | — | the result card, rendered empty inside each section's `<template>`; the client fills one per result |

`name` is the name of the search root the brick belongs to. You only need it when the page holds several roots: see
[Several searches on one page](README.md#several-searches-on-one-page).

**Required bricks.** The client checks the markup when it starts. A root must hold a panel, an input, an empty state
and an unavailable message, and each section keeps its list, its count and its card template. If one is missing, the
client does not start and the browser console names what is missing:

```text
[meilifacets] the markup does not meet the contract: search-empty, search-unavailable
```

The toggle is not checked, but it is the only control that opens the panel.

To change the wording of the messages and the magnifier's label, translate them: see
[Translating the interface](../customising/translations.md).

## Free layout

The client assumes no order and no nesting. It finds each brick by its hook inside the root, then each field of a
card inside its result. A template can put the empty state before the sections, the field after them, and the
magnifier after the panel:

```blade
<x-meilifacets::search>
    <x-meilifacets::search.panel class="header-search-panel">
        <x-meilifacets::search.empty-state />
        <div class="header-search-columns">
            <x-meilifacets::search.section type="post" limit="2" />
            <x-meilifacets::search.section type="product" limit="3" />
        </div>
        <x-meilifacets::search.input />
        <x-meilifacets::search.unavailable />
    </x-meilifacets::search.panel>
    <x-meilifacets::search.toggle />
</x-meilifacets::search>
```

Sections are searched in the order they are placed, and ↓ and ↑ go through their results in that order.

Every brick must stay inside `<x-meilifacets::search>`: the client binds inside the root only, and reports a brick
it finds outside in the browser console.

## Guarding a product section

A section whose type is not searchable throws when the page renders (see below). Products are searchable only while
WooCommerce is active and products are indexed. If the same template can run without WooCommerce, guard the section:

```blade
@if (function_exists('wc_get_product'))
    <x-meilifacets::search.section type="product" />
@endif
```

The default composition (`<x-meilifacets::search />` with no content) needs no guard: it renders one section per type
that is searchable at that moment.

## Rules enforced at render

These errors are thrown while the page renders, with these messages:

| Cause | Message |
| --- | --- |
| a section asks for a type the root does not search: not declared in `SearchableTypes`, or not indexed by MeiliScout | `Post type "event" is not searchable: declare it in SearchableTypes and index it in MeiliScout. Searchable here: product, post, page.` |
| two sections of the same type in one root | `The search "search" already holds a section for "post": place one section per type.` |
| a `limit` that is not a whole number of at least 1 (`0`, `2.5`, `abc`) | `The search section for "post" asks for 2.5 results: it shows a whole number of them, at least 1.` |
| two roots with the same name | `A search named "search" is already rendered on this page: give each root its own name.` |
| a brick without `name` on a page with several roots | `Name the search: 2 are declared (header, drawer).` |
| a brick naming a root that does not exist | `No search named "footer". Declared: header, drawer.` |

`limit="2"` and `:limit="2"` are both accepted. Two roots can each hold a section of the same type.

## Overriding a brick's view

Each brick's view can be overridden in the theme, under
`<theme>/resources/views/modules/meilifacets/components/`. See [Overriding views](../customising/views.md) for how the
lookup works.

| View | Path under `…/modules/meilifacets/components/` |
| --- | --- |
| root | `search.blade.php` |
| bricks | `search/toggle.blade.php`, `search/panel.blade.php`, `search/input.blade.php`, `search/section.blade.php`, `search/card.blade.php`, `search/empty-state.blade.php`, `search/unavailable.blade.php` |

An overridden view can move its elements and change its classes, but it keeps its `data-meili` hooks, wherever it
places them. Print them with `{{ $hook('…') }}`, as the module's views do.

| View | Hooks to keep |
| --- | --- |
| `search` | `search` on the root, with `data-search="{{ $root->name }}"` and `{{ $contract }}` |
| `search/toggle` | `search-toggle` on a `<button>` that keeps `aria-expanded` and `aria-controls="{{ $panelId }}"` |
| `search/panel` | `search-panel` with `id="{{ $panelId }}"`, and `search-status` inside it |
| `search/input` | `search-input` on the `<input>`, with `id="{{ $inputId }}"`; `search-field` on its wrapper is optional |
| `search/section` | `search-section` with `data-type` and `data-limit`; inside it `search-count`, `search-results` with `id="{{ $listboxId }}"`, and `search-card-template`, whose first element carries `card`; `search-see-all` is optional |
| `search/card` | `url` and `title`; `image`, `summary` and `price` if the card shows them |
| `search/empty-state` | `search-empty` |
| `search/unavailable` | `search-unavailable` |

Without `search-field`, the field has no magnifier and does not stick to the top of the panel.

### The result card

Overriding `search/card.blade.php` changes the result card for every section of the site. The section already wraps
the card in `<li role="option" data-meili="card">`: your card does not carry the option.

The card component prepares one element per field: `$link`, `$image`, `$title`, `$summary` and `$price`. Each element
holds its attributes and its content; the view only places them. The only hook the client strictly needs is `url`,
the link Enter follows, which `$link->attributes` carries:

```blade
<a {{ $attributes->class('result') }} {{ $link->attributes }}>
    @if ($title->isPresent())
        <span {{ $title->attributes->class('result-title') }}>{{ $title }}</span>
    @endif
    @if ($price->isPresent())
        <span {{ $price->attributes->class('result-price') }}>{{ $price }}</span>
    @endif
    @if ($image->isPresent())
        <img {{ $image->attributes->class('result-image') }} alt="" loading="lazy" decoding="async">
    @endif
</a>
```

The client removes from each result the image, summary or price it does not have. The title and the summary are
written as text, with matched words in `<mark>`; the price is WooCommerce's HTML. The thumbnail is decorative
(`alt=""`): the link already reads the title.

To give one content type its own card, see [A card per type](types.md#a-card-per-type).

## Watch out

- A manual composition searches only the sections it places. A post type indexed later does not appear in it until
  you add its section.
- Leaving out the empty state or the unavailable message stops the client: they are required even if you hide them
  with CSS.
- A view copied into the theme keeps its old markup when the module changes its own. Compare your overrides with the
  module's views at each upgrade.

## See also

- [Site search](README.md)
- [Searchable content types](types.md)
- [Overriding views](../customising/views.md)
- [`data-meili` hooks](../reference/hooks.md)
- [Errors and console messages](../reference/errors.md)
