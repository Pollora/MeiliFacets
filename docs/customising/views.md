# Overriding views

This page covers how a theme replaces the markup of a module component, and what that markup must keep for the
browser client to work.

You want to…

- [replace the markup of one component](#the-cascade);
- [know what a view receives](#what-a-view-receives);
- [know what you must keep](#hooks-not-classes);
- [check the requirements the client cannot check](#five-requirements-that-are-not-hooks);
- [understand why the client did not start](#when-the-contract-is-not-met);
- [change an icon without overriding](#slots-or-overrides);
- [keep up with module updates](#upgrading).

## The cascade

Copy the module's view into your theme, under the same path:

```text
Modules/MeiliFacets/resources/views/components/listing/facet.blade.php
<theme>/resources/views/modules/meilifacets/components/listing/facet.blade.php
```

The module looks in the theme first. A file in the theme replaces that one view; every other view keeps following the
module's updates. Override as few views as you can.

An example: the listing card, with the price above the title.

```blade
{{-- <theme>/resources/views/modules/meilifacets/components/listing/card.blade.php --}}
<article {{ $attributes->class('meilifacetsCard') }}>
    <a {{ $link->attributes->class('meilifacetsCardLink') }}>
        @if ($image->isPresent())
            <img {{ $image->attributes->class('meilifacetsCardImage') }} decoding="async">
        @endif
        @if ($price->isPresent())
            <p {{ $price->attributes->class('meilifacetsCardPrice') }}>{{ $price }}</p>
        @endif
        @if ($title->isPresent())
            <{{ $heading }} {{ $title->attributes->class('meilifacetsCardTitle') }}>{{ $title }}</{{ $heading }}>
        @endif
    </a>
</article>
```

The views that can be overridden:

| Folder | Views |
| --- | --- |
| `components/` | `listing`, `search` |
| `components/listing/` | `active-filters`, `active-values`, `apply`, `card`, `drawer`, `drawer-opener`, `facet`, `facets`, `pagination`, `price`, `reset`, `reset-icon`, `results`, `search`, `sort`, `sort-radios`, `toggle`, `total`, `unavailable` |
| `components/listing/price/` | `range`, `fields`, `hidden` |
| `components/search/` | `card`, `empty-state`, `input`, `panel`, `section`, `toggle`, `unavailable` |

`listing/card` renders both the cards of the first page and the `<template>` the browser clones. To render your own
fields in it, see [Your own card](card.md).

## What a view receives

A view backed by a component class receives the public properties and public methods of that class, and the attribute
bag (`$attributes`). Every component of the module has two helpers:

- `$hook('name')` prints `data-meili="name"`;
- `$scrollMark()` prints `data-meili-scroll` when the component was given the `scroll` attribute, and nothing
  otherwise.

Components that belong to a listing also receive `$listing` (the resolved listing) and `$ids` (the element ids, built
from the listing's name). Those that belong to a search receive `$root` and `$ids`.

| View | Also receives |
| --- | --- |
| `listing` | `$contract` (the contract version attribute) |
| `listing/results` | `$cards`, `$items`, `$priority($rank)` |
| `listing/card` | `$binding`, `$priority`, `$heading`, `$link`, `$image`, `$title`, `$price` |
| `listing/facets` | `$applyMode`, `$needsApplyButton`, `$collapsible`, `$withApply`, `$componentFor($filter)` |
| `listing/facet` | `$facet`, `$values`, `$presentation`, `$collapsible`, `$inputType()`, `$inputName()`, `$panelId()`, `$labelId($value)`, `$countId($value)`, `$countLabel($value)`, `$disclosure()`, `$hasFoldedValues()`, `$isShown()`, `$marksPresentation()` |
| `listing/price` | `$filter`, `$money`, `$collapsible`, `$bounds()`, `$handles()`, `$fill()`, `$readout()`, `$showsSlider()`, `$showsFields()`, `$isShown()`, `$panelId()`, `$disclosure()` |
| `listing/sort`, `listing/sort-radios` | `$choices`, `$selected`, `$caption`, `$widget`, `$collapsible`, `$disclosure()`, `$panelId()`, `$choiceName()` |
| `listing/pagination` | `$pagination` |
| `listing/total` | `$label` |
| `listing/active-filters` | `$count`, `$label` |
| `listing/active-values` | `$values` |
| `listing/apply` | `$shape`, `$visibleInDrawer`, `$badge`, `$label()`, `$onlyInDrawer()` |
| `listing/reset`, `listing/reset-icon` | `$shape`, `$hasNothingToClear()`, `$defaultIconUrl()` |
| `listing/drawer` | `$drawerId`, `$titleId`, `$media`, `$heading`, `$isSideSheet()`, slot `$footer` |
| `listing/drawer-opener` | `$drawerId`, `$badge`, `$defaultIconUrl()`, slot `$icon` |
| `listing/search` | `$action`, `$kept`, `$parameter`, `$term`, `$inputId`, `$maxLength`, `$label()` |
| `search` | `$contract`, `$sectionTypes()`, slot `$icon` |
| `search/toggle` | `$panelId`, `$defaultIconUrl()`, slot `$icon` |
| `search/panel` | `$panelId` |
| `search/input` | `$inputId` |
| `search/section` | `$type`, `$limit`, `$headingId`, `$listboxId` |
| `search/card` | `$link`, `$image`, `$title`, `$summary`, `$price` |

`listing/toggle` and the three views under `listing/price/` are anonymous components: each opens on `@props` and
receives only what is listed there. `toggle` receives `disclosure`; `price/range` receives `label`, `readout`, `fill`,
`handles`, `bounds` and `money`; `price/fields` receives `handles`, `bounds` and `money`; `price/hidden` receives
`handles`.

Read the module's own view before you write yours: it shows how each value is used.

## Hooks, not classes

The browser client never looks for a class. It finds each part of a component by its hook, `data-meili="…"`. Classes,
tags and styles belong to the theme; hooks belong to the contract.

- Keep every `data-meili` attribute the module's view writes, wherever you move it.
- Keep `{{ $scrollMark() }}` where the module's view has it.
- When you wrap a component in a new outer element, move the outer hook to the new outer element. The `facet` hook,
  for example, goes on the outermost element of a facet: that is the element the client hides when the facet has
  nothing left to show.
- Keep the `id` and `aria-*` attributes the view writes. The client and assistive technologies rely on them (see
  [Accessibility and motion](../accessibility.md)).

An example: a facet as a `<details>` element. The `facet` hook and `data-taxonomy` move to `<details>`, the rest stays.

```blade
{{-- <theme>/resources/views/modules/meilifacets/components/listing/facet.blade.php --}}
<details {{ $attributes->class('themeFacet') }} data-taxonomy="{{ $facet->taxonomy }}"
         @unless ($isShown()) hidden @endunless {{ $hook('facet') }} {{ $scrollMark() }}>
    <summary>{{ $facet->label }}</summary>
    <fieldset>
        <legend class="screen-reader-text">{{ $facet->label }}</legend>
        <ul>
            @foreach ($values as $value)
                <li @if ($value->folded) hidden @endif {{ $hook('facet-value') }}>
                    <label>
                        <input type="{{ $inputType() }}" name="{{ $inputName() }}" value="{{ $value->slug }}"
                               aria-labelledby="{{ $labelId($value) }}" aria-describedby="{{ $countId($value) }}"
                               @checked($value->selected) {{ $hook('input') }}>
                        <span id="{{ $labelId($value) }}">{{ $value->label }}</span>
                        <span id="{{ $countId($value) }}" {{ $hook('count') }}>{{ $countLabel($value) }}</span>
                    </label>
                </li>
            @endforeach
        </ul>
        <button type="button" aria-expanded="false" @unless ($hasFoldedValues()) hidden @endunless {{ $hook('more') }}>
            <span {{ $hook('more-label') }}>{{ __('Show more') }}</span>
            <span hidden {{ $hook('less-label') }}>{{ __('Show less') }}</span>
        </button>
    </fieldset>
</details>
```

This example drops the module's `collapsible` mode: a facet placed with `collapsible` expects the `toggle` and `panel`
hooks, which this view does not render. Place it without `collapsible`.

The hooks each view needs, and which ones are required, are listed in [`data-meili` hooks](../reference/hooks.md).

## Five requirements that are not hooks

The client checks hooks when it starts. It cannot check the following, and a view that forgets them breaks the client
without any message about the contract:

| A view must render | Otherwise |
| --- | --- |
| `data-listing="<name>"` on the listing root | the client binds no listing; the console names the hooks left outside any root |
| `name="{{ $inputName() }}"` on a facet's `<input>` | the client finds a value's taxonomy by that name: the boxes do nothing |
| a single root element in the card `<template>` | only the first element is cloned: the rest of the card disappears, and a template without an element paints no card |
| `hidden` on a facet while `$isShown()` is false | a filtered page also renders values without results: the legend stays above nothing until the first search |
| `hidden` on a sort option when `$choice->hidden` | an option that would empty the grid stays offered until the first search |

The same goes for the site search root: `data-search="{{ $root->name }}"` must stay on it.

## When the contract is not met

When a required hook is missing, the client does not start. The page stays as the server rendered it, and the browser
console says why:

```text
[meilifacets] the markup does not meet the contract: card-template, facet > more
```

`facet > more` reads « a `facet` that holds values renders no `more` ». Other messages:

| Console message | Cause |
| --- | --- |
| `the markup does not meet the contract: …` | the hooks listed are missing |
| `the client binds inside [data-listing] only. Move inside <x-meilifacets::listing> : …` | hooks rendered outside the root; the same for `[data-search]` |
| `the page describes no listing named "…"` | the root's `data-listing` value names no listing of the page |
| `no data was published under @meilifacets/listing.` | the page rendered a root, but the server published no data for the client |

The listing root and the search root also carry `data-meili-contract`, the contract version. It changes when a hook is
renamed or removed, never when one is added. The server writes it and the client compares it with its own: it catches
a browser running an old cached client against new markup. It does not catch an override that has fallen behind,
since the version is written by the module, not by your view. That is what the hook check is for.

## Slots or overrides

Some components take an `icon` slot, so a decorative icon changes without overriding a view:

| Component | Default icon |
| --- | --- |
| `<x-meilifacets::search>` and `<x-meilifacets::search.toggle>` | `images/search.svg` |
| `<x-meilifacets::listing.drawer-opener>` | `images/filters.svg` |
| `<x-meilifacets::listing.reset shape="icon">` | `images/trash.svg` |

```blade
<x-meilifacets::listing.drawer-opener>
    <x-slot:icon><svg viewBox="0 0 16 16" width="16" height="16">…</svg></x-slot:icon>
</x-meilifacets::listing.drawer-opener>
```

An empty slot removes the icon. The slot is wrapped in `aria-hidden="true"`: the icon is decoration, and the button
is named by its text or its `aria-label`. An icon that carries meaning does not go through the slot: override the view
instead.

## Upgrading

An overridden view keeps its markup when the module changes its own. After each update of the module, compare every
view in `<theme>/resources/views/modules/meilifacets/` with the module's new version, then load a page with a listing
and a page with the search and check the browser console.

## Watch out

- **The theme folder must exist when WordPress sets up the theme.** The module adds
  `<theme>/resources/views/modules/meilifacets` to its view paths on `after_setup_theme`, and only if the folder is
  there.
- **`<theme>` is the active theme's own folder** (`get_stylesheet_directory()`). With a child theme, the overrides go
  in the child theme; a parent theme's overrides are not read.
- **A view cache can hide your change.** Run `php artisan view:clear` after adding an override.
- **Some components do not forward their attribute bag.** `class="…"` set on `<x-meilifacets::listing.results>`,
  `facets`, `sort`, `pagination`, `reset` or `active-filters` is dropped without an error. Wrap the component, or
  override its view.
- **The module's classes are not a stable interface.** Style by hooks and state attributes (see
  [Styles and design tokens](styles.md)), or by your own classes in your own views.

## See also

- [Your own card](card.md)
- [Styles and design tokens](styles.md)
- [Accessibility and motion](../accessibility.md)
- [`data-meili` hooks](../reference/hooks.md)
- [Blade components](../reference/components.md)
- [Errors and console messages](../reference/errors.md)
- [Troubleshooting and FAQ](../troubleshooting.md)
