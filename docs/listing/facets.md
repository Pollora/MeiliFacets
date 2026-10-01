# Facets

This page covers the term facets of a product listing: which taxonomies the shop is filtered by, how each facet
behaves, and where a template places it.

You want to…

- [change which taxonomies the shop is filtered by](#declaring-the-facets);
- [keep the module's facets and add one](#starting-from-the-defaults);
- [name facets without writing a taxonomy in a template](#naming-facets-with-an-enum);
- [show one facet somewhere special and the rest in a group](#placing-facets);
- [show only the sub-categories of the current category](#sub-level-navigation);
- [allow one value or several](#one-value-or-several);
- [order values by count, by the taxonomy's own order, or by name](#value-order);
- [show more or fewer values before “Show more”](#how-many-values);
- [show values as pills, or in a presentation of your own](#presentations);
- [fold facets into collapsible sections](#collapsible-facets);
- [keep or hide “Uncategorized”](#the-default-term).

## Minimal example

Out of the box, a WooCommerce shop is filtered by category and brand. To filter by colour as well, bind your own list
of facets in a service provider of the project:

```php
<?php

declare(strict_types=1);

namespace App\Listing;

use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\WooCommerceFacets;

final class ShopFacets implements ProductFacets
{
    public function __construct(private readonly WooCommerceFacets $defaults) {}

    public function all(): array
    {
        return [
            ...$this->defaults->all(),
            new Facet('pa_color', __('Color')),
        ];
    }
}
```

```php
// In the register() method of a project service provider
$this->app->scoped(\Modules\MeiliFacets\Contracts\ProductFacets::class, \App\Listing\ShopFacets::class);
```

```blade
<x-meilifacets::listing>
    <x-meilifacets::listing.facets />
    <x-meilifacets::listing.results />
</x-meilifacets::listing>
```

The colour facet appears after the category and the brand, and its URL parameter is `f_pa_color` (see
[How a listing works](README.md#the-url-is-the-state) to give it a readable name).

## Declaring the facets

`ProductFacets` has one method, `all()`, which returns the filters of the product listing in the order they are shown:
`Facet` and `ChildTermsFacet` objects for taxonomies, and at most one `PriceFilter` (see [Price filter](price.md)).

The module binds `WooCommerceFacets` with `scopedIf`: a binding made by the project wins. Use `scoped`, as above, so
that the list is built once per request.

| Default facet | Class | Taxonomy | Label | Name | Value order |
| --- | --- | --- | --- | --- | --- |
| Category | `ChildTermsFacet` | `product_cat` | `Category` | `product_cat` | by name |
| Brand | `Facet` | `product_brand` | `Brand` | `product_brand` | by name |

The default list has no price filter, so the “On sale” sort is not offered until you declare one (see
[Results, sorting and pagination](results-sort-pagination.md#sorting)).

Any taxonomy of an indexed post type can be a facet: the module makes every one of them filterable in the index, under
`facets.<taxonomy>`. A taxonomy of a post type MeiliScout does not index has no field, and its facet stays empty. See
[What gets indexed](../indexing/README.md).

### The `Facet` constructor

```php
new Facet(
    taxonomy: 'pa_color',
    label: __('Color'),
    selection: SelectionMode::Multiple,
    order: DisplayOrder::Count,
    visible: 10,
    cap: 30,
    defaultTerm: DefaultTermVisibility::Hidden,
    name: ShopFacet::Color,
    presentation: Presentation::Control,
);
```

| Argument | Type | Default | Effect |
| --- | --- | --- | --- |
| `taxonomy` | `string` | required | the taxonomy filtered on |
| `label` | `string` | required | the legend of the facet, already translated |
| `selection` | `SelectionMode` | `Multiple` | `Multiple` renders checkboxes, `Single` renders radios ([below](#one-value-or-several)) |
| `order` | `DisplayOrder` or `ValueOrder` | `DisplayOrder::Count` | the order values are read in ([below](#value-order)) |
| `visible` | `int` | `10` | values shown before “Show more” |
| `cap` | `int` | `30` | values kept from what the engine returned ([below](#how-many-values)) |
| `defaultTerm` | `DefaultTermVisibility` | `Hidden` | whether the taxonomy's fallback term is offered ([below](#the-default-term)) |
| `name` | `string` or backed enum | the taxonomy | what a template designates the facet by |
| `presentation` | `ValuePresentation` | `Presentation::Control` | how values look ([below](#presentations)) |

Classes and enums live under `Modules\MeiliFacets\Listing` (`Facet`, `ChildTermsFacet`, `NameOrder`) and
`Modules\MeiliFacets\Enums` (`SelectionMode`, `DisplayOrder`, `DefaultTermVisibility`, `Presentation`).

## Starting from the defaults

Decorate `WooCommerceFacets` rather than rewriting it, as in the [minimal example](#minimal-example): the defaults
keep their translated labels and their value order, and a later version of the module can improve them without you
copying the change.

To drop one, filter it by name:

```php
public function all(): array
{
    return array_values(array_filter(
        $this->defaults->all(),
        static fn (Placeable $filter): bool => $filter->name !== 'product_brand',
    ));
}
```

`Placeable` is `Modules\MeiliFacets\Contracts\Placeable`, the type shared by facets and the price filter.

## Naming facets with an enum

A template designates a facet by its name, never by its taxonomy. Without `name`, the name is the taxonomy. With a
backed enum, templates carry no free string, and static analysis catches a typo:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ShopFacet: string
{
    case Category = 'category';
    case Brand = 'brand';
    case Color = 'color';
    case Price = 'price';
}
```

```php
<?php

declare(strict_types=1);

namespace App\Listing;

use App\Enums\ShopFacet;
use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Enums\DisplayOrder;
use Modules\MeiliFacets\Enums\Presentation;
use Modules\MeiliFacets\Enums\PricePart;
use Modules\MeiliFacets\Listing\ChildTermsFacet;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Listing\PriceFilter;

final class ShopFacets implements ProductFacets
{
    public function __construct(private readonly NameOrder $names) {}

    public function all(): array
    {
        return [
            new ChildTermsFacet('product_cat', __('Category'), order: $this->names, name: ShopFacet::Category),
            new Facet('product_brand', __('Brand'), order: $this->names, name: ShopFacet::Brand),
            new Facet(
                'pa_color',
                __('Color'),
                order: DisplayOrder::Declared,
                name: ShopFacet::Color,
                presentation: Presentation::Pill,
            ),
            new PriceFilter(__('Price'), [PricePart::Slider, PricePart::Fields], name: ShopFacet::Price),
        ];
    }
}
```

Names must be unique within a listing. Two filters sharing a name fail at render:

```text
Facets "Brand" and "Maker" answer to the same name "brand" in listing "products". Declare `name:` on all but one of them.
```

## Placing facets

Two components place term facets:

| Component | Renders |
| --- | --- |
| `<x-meilifacets::listing.facet facet="…" />` | one facet, designated by name |
| `<x-meilifacets::listing.facets />` | every filter not already placed on its own, in declared order, price filter included, then the Apply button in `submit` mode |

```blade
@use('App\Enums\ShopFacet')

<x-meilifacets::listing>
    <div class="shop-toolbar">
        <x-meilifacets::listing.facet :facet="ShopFacet::Category" class="shop-toolbar__categories" />
    </div>
    <aside>
        <x-meilifacets::listing.facets />
    </aside>
    <x-meilifacets::listing.results />
</x-meilifacets::listing>
```

`listing.facet` accepts a name (`facet="product_brand"`), a backed enum (`:facet="ShopFacet::Brand"`) or a declaration
(`:facet="$facet"`).

| Attribute of `listing.facet` | Default | Effect |
| --- | --- | --- |
| `facet` | required | the facet to render |
| `name` | the only listing | the listing it belongs to |
| `presentation` | the facet's own | `control`, `pill`, or a bound `ValuePresentation` ([below](#presentations)) |
| `collapsible` | `false` | a toggle opens and closes the values ([below](#collapsible-facets)) |
| `scroll` | `false` | accepted; no facet gesture scrolls the page (see [How a listing works](README.md#scrolling-back-to-the-top)) |

| Attribute of `listing.facets` | Default | Effect |
| --- | --- | --- |
| `name` | the only listing | the listing it belongs to |
| `collapsible` | `false` | passed to every facet it renders |
| `with-apply` | `true` | renders the Apply button in `submit` mode. `:with-apply="false"` leaves it to an Apply placed elsewhere, typically in the [drawer footer](drawer.md) |
| `scroll` | `false` | its Apply button brings the top of the listing back into view |

The attribute bag of `listing.facet` lands on its `<fieldset>`, merged with the module's class. `listing.facets` takes
no attribute bag. A group with nothing left to render and no Apply button renders no markup at all.

Rules:

- **Place single facets before the group.** The group renders what is left at the moment it renders. A facet placed
  after the group is rendered twice, which fails.
- **Each facet once per page.** A second rendering fails rather than duplicating inputs and ids:

  ```text
  Facet "category" is rendered twice on this page: its inputs and ids would be duplicated. Place it on its own before <x-meilifacets::listing.facets>, which shows what is left.
  ```

- **A name nobody declared fails**, listing the declared names:
  `No facet named "colour" in listing "products". Declared: category, brand, color, price.`
- **Each kind has its component.** A price filter goes through `<x-meilifacets::listing.price>`; given to
  `listing.facet`, it fails with `"price" in listing "products" is a PriceFilter: place it with the component of its
  own kind.`

## Sub-level navigation

A `ChildTermsFacet` takes the same arguments as a `Facet`, and offers the level the visitor is on:

- on the shop page, the top-level terms;
- on a term archive of its own taxonomy, the terms directly under that term;
- on a term with no children, nothing: the facet is hidden.

It is what makes a hierarchical taxonomy of a hundred terms over three levels usable. Moving sideways, to a sibling
category, is the job of a breadcrumb or a menu.

A plain `Facet` of the taxonomy the path filters on is no longer offered on that archive: it would hold a single
value. See [How a listing works](README.md#the-browsed-term).

## One value or several

`SelectionMode::Multiple` (the default) renders checkboxes. Values of the same facet combine with OR, facets combine
with AND: ticking Acme and Globex in Brand, then Red in Color, lists the red products of either brand.

`SelectionMode::Single` renders radios: one value at a time.

The count beside each value is the number of results that value would give:

- for a multiple-selection facet holding at least one value, the count ignores the facet's own selection, so ticking
  Acme does not turn Globex's count to zero. This takes one extra query per such facet;
- every other facet is counted on the main query.

A value with no result is hidden, unless it is selected.

## Value order

The engine always decides **which** values come back, by count, so that the cap keeps the most represented ones. The
facet decides **in which order** they are read:

| `order` | Values read |
| --- | --- |
| `DisplayOrder::Count` (default) | most results first |
| `DisplayOrder::Declared` | in the order the taxonomy lists its terms (`get_terms()`). On a WooCommerce attribute, that is the attribute's default sort order: custom ordering, name, numeric name or term ID |
| `NameOrder` | alphabetically, as the site's language sorts: accents in place, `9 ml` before `10 ml` |
| your own `ValueOrder` | as your `compare()` decides |

`NameOrder` needs the `intl` PHP extension. Without it, names are compared with `strnatcasecmp()`, which sorts
accented letters after the whole ASCII alphabet. Get `NameOrder` from the container (inject it, as `ShopFacets` does
above): it is built with the collator of the current locale. `new NameOrder()` has no collator and always falls back.

An attribute set to custom ordering whose terms were never dragged into place falls back to alphabetical order:
`Declared` follows the decision, it does not make it.

A custom order implements `Modules\MeiliFacets\Contracts\ValueOrder`:

```php
<?php

declare(strict_types=1);

namespace App\Listing;

use Modules\MeiliFacets\Contracts\ValueOrder;
use Modules\MeiliFacets\Listing\FacetValue;

final readonly class SelectedFirst implements ValueOrder
{
    public function compare(FacetValue $first, FacetValue $second): int
    {
        return $second->selected <=> $first->selected;
    }
}
```

`compare()` returns a negative number when the first value reads before the second, and zero to keep the engine's
order. A `FacetValue` exposes `slug`, `label`, `count`, `selected` and `folded`.

## How many values

Three ceilings apply, in this order:

| Ceiling | Default | Set by | Cuts |
| --- | --- | --- | --- |
| `engine.max_facet_values` | `1000` | `config/meilifacets.php`, written to the index | the distinct values the engine returns per facet, best counted first |
| `cap` | `30` | the facet | the values the module keeps from what it received |
| `visible` | `10` | the facet | the values shown before “Show more”; the rest are rendered hidden, never lost |

- Values are ordered first, then folded: what the visitor reads is the head of the order the facet asked for.
- A selected value is always shown, wherever it falls.
- `cap` cannot exceed the engine ceiling: a facet declared with `cap: 2000` gets at most 1000 values, without error.
- The engine cuts by count over the whole catalogue, before a `ChildTermsFacet` narrows to the current level: a value
  rare across the shop may be the only one that matters on its page. Size `engine.max_facet_values` on the size of
  the largest taxonomy, not on `cap`, then reindex.

When a facet comes back with exactly as many values as the ceiling (or exactly 100, the engine's own default before
the module's setting is pushed), the module reports a `FacetTruncated` exception to the application's error handler,
without breaking the page:

```text
Facet "pa_color" returned 1000 values, the ceiling the engine applies. Values beyond it were dropped before the facet could narrow them. Raise meilifacets.engine.max_facet_values, then reindex to push the setting. A taxonomy that happens to use exactly that many values reports this too.
```

“Show more” reads the whole facet without querying the engine, and turns into “Show less”.

## Presentations

By default, values are checkboxes (or radios). A facet can present them as pills:

```php
new Facet('pa_size', __('Size'), presentation: Presentation::Pill)
```

The `<fieldset>` then carries `data-presentation="pill"`, which the module's stylesheet draws. The native input stays
in the page, visually hidden. A facet presented as `Control` carries no attribute. The markup is the same in every
presentation: only the attribute changes.

A template can override the presentation of one facet: `presentation="pill"` or `presentation="control"`.

A theme declares its own presentations with an enum implementing `Modules\MeiliFacets\Contracts\ValuePresentation`,
and draws them in its own CSS:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

use Modules\MeiliFacets\Contracts\ValuePresentation;

enum Swatch: string implements ValuePresentation
{
    case Color = 'swatch';

    public function slug(): string
    {
        return $this->value;
    }

    public function allowsSingleSelection(): bool
    {
        return false;
    }
}
```

```blade
@use('App\Enums\Swatch')

<x-meilifacets::listing.facet facet="pa_color" :presentation="Swatch::Color" />
```

```css
[data-meili="facet"][data-presentation="swatch"] [data-meili="facet-value"] {
    display: inline-flex;
}
```

`slug()` is the value of `data-presentation`. `allowsSingleSelection()` says whether the presentation can show a
radio: a radio that is hidden cannot be unchecked. A `SelectionMode::Single` facet under a presentation that refuses
it fails as soon as it is declared (or as soon as the template overrides it):

```text
Facet "size" holds one value at a time, so its values cannot be presented as "pill": they would be radios, which cannot be unchecked. Present it as "control", or declare it with SelectionMode::Multiple.
```

## Collapsible facets

With `collapsible`, the legend becomes a toggle button and the values a panel that opens and closes:

```blade
<x-meilifacets::listing.facets collapsible />
```

- Below `48em`, a collapsible facet is an accordion section, full width; sections open independently.
- From `48em`, it is a pill whose panel floats under it. One panel is open at a time; Escape and a click outside close
  it. A panel that would overflow the viewport on the right is aligned to the pill's right edge.
- The toggle shows how many values are selected in the facet.

This is what the [mobile drawer and filter bar](drawer.md) is built on.

## The default term

Every facet leaves out its taxonomy's fallback term (“Uncategorized”), read from the `default_term_<taxonomy>` or
`default_<taxonomy>` option. It says that a product was filed nowhere, which is not a way to browse a catalogue. The
products themselves stay in the listing.

When the fallback term is a real one an editor chose, declare
`defaultTerm: DefaultTermVisibility::Shown`.

## Watch out

- **Facet names are unique within a listing**, price filter included.
- **A taxonomy MeiliScout does not index has no field**: its facet renders hidden. Check the indexed post types, then
  reindex.
- **Single facets before the group**, each facet once per page.
- **`NameOrder` from the container**, not `new NameOrder()`.
- **Changing `engine.max_facet_values` needs a reindex** to reach the index.
- **The client counts like the module's default.** After the first gesture, the browser rebuilds the queries itself,
  with the rule described in [One value or several](#one-value-or-several). A project that rebinds `FacetCounter`
  changes the first render only.

## See also

- [Price filter](price.md), [Mobile drawer and filter bar](drawer.md), [How a listing works](README.md)
- [PHP extension points](../customising/php.md), [Overriding views](../customising/views.md)
- [Blade components](../reference/components.md), [PHP contracts](../reference/contracts.md),
  [Index settings and document fields](../reference/index-settings.md)
