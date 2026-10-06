# Price filter

This page covers the price filter of a WooCommerce product listing: declaring it, placing it, what it filters on, its
URL parameters and how to redraw it.

You want to…

- [add a price filter](#declaring-it);
- [choose fields, a slider, or both](#fields-slider-or-both);
- [place it apart from the other facets](#placing-it);
- [know which price a product is matched on](#what-price-means);
- [rename `min_price` and `max_price`](#bounds-in-the-url);
- [offer the “On sale” sort](#the-on-sale-sort);
- [redraw the slider or the fields](#overriding-the-three-sub-views).

## Minimal example

The price filter is declared with the facets, in your `ProductFacets` binding (see
[Facets](facets.md#declaring-the-facets)):

```php
<?php

declare(strict_types=1);

namespace App\Listing;

use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Enums\PricePart;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\WooCommerceFacets;

final class ShopFacets implements ProductFacets
{
    public function __construct(private readonly WooCommerceFacets $defaults) {}

    public function all(): array
    {
        return [
            ...$this->defaults->all(),
            new PriceFilter(__('Price'), [PricePart::Slider, PricePart::Fields]),
        ];
    }
}
```

```php
// In the register() method of a project service provider
$this->app->scoped(\Modules\MeiliFacets\Contracts\ProductFacets::class, \App\Listing\ShopFacets::class);
```

`<x-meilifacets::listing.facets />` renders it in declared order, after the category and the brand.

## Declaring it

```php
new PriceFilter(label: __('Price'), parts: [PricePart::Fields], name: 'price');
```

| Argument | Type | Default | Effect |
| --- | --- | --- | --- |
| `label` | `string` | required | the legend of the block, already translated |
| `parts` | `list<PricePart>` | `[PricePart::Fields]` | what is drawn ([below](#fields-slider-or-both)) |
| `name` | `string` or backed enum | `price` | what a template designates it by. Unique within the listing, like a facet name |

`PriceFilter` is `Modules\MeiliFacets\Listing\PriceFilter`; `PricePart` is `Modules\MeiliFacets\Enums\PricePart`.

Declare one price filter per listing: the listing holds a single price range, and the client binds a single pair of
bounds.

The price filter only works on documents that carry a price, which the module indexes for WooCommerce products. See
[Indexed prices](../indexing/prices.md).

### Fields, slider, or both

| `parts` | Renders |
| --- | --- |
| `[PricePart::Fields]` (default) | two number fields, From and To, with the currency symbol |
| `[PricePart::Slider]` | a two-handle slider with a readout and the bounds of the catalogue; the bounds travel in hidden inputs |
| `[PricePart::Slider, PricePart::Fields]` | the slider above the two fields; moving one moves the other |

With a slider, the label is shown above the track, and the `<legend>` is kept for screen readers only.

## Placing it

Inside `<x-meilifacets::listing.facets />`, the price filter takes its declared place. To put it somewhere else, place
it on its own **before** the group, which then leaves it out:

```blade
<x-meilifacets::listing>
    <x-meilifacets::listing.price facet="price" collapsible />
    <x-meilifacets::listing.facets />
    <x-meilifacets::listing.results />
</x-meilifacets::listing>
```

| Attribute | Default | Effect |
| --- | --- | --- |
| `facet` | required | the filter's name, a backed enum, or the declaration |
| `name` | the only listing | the listing it belongs to |
| `collapsible` | `false` | a toggle opens and closes the block, like a [collapsible facet](facets.md#collapsible-facets) |
| `scroll` | `false` | accepted; changing a price does not scroll the page |

The attribute bag lands on the `<fieldset>`. A price filter given to `<x-meilifacets::listing.facet>` fails with
`"price" in listing "products" is a PriceFilter: place it with the component of its own kind.`

## How it behaves

- **Bounds.** The slider and the fields span the prices of the products that match every other filter, rounded down
  and up to whole units. They follow the facets: checking a brand narrows them; checking a variation attribute, such as
  a size, makes them span the prices of the matching variants.
- **Hidden when there is nothing to choose.** When no product matches, or every matching product has the same
  price, the block renders hidden.
- **When it filters.** A field filters when its value is committed (Enter or leaving the field); a handle filters
  when it is released, or when the key that moves it is let go. Dragging previews, releasing filters. In `submit`
  mode, the range waits for Apply like a checked box (see [How a listing works](README.md#instant-or-on-submit)).
- **A bound at the edge is no filter.** A field emptied, or a handle moved back to the end of the track, removes that
  bound.
- **Keyboard.** Each handle is a `role="slider"` button: the arrow keys move it by one unit, Shift with an arrow by a
  tenth of the range, Home and End to the ends.
- **Active value and count.** A held range shows as one pill in `<x-meilifacets::listing.active-values>` and counts as
  one active filter, whatever its ends.

## What “price” means

The price is the one the shop displays, taxes included or not as WooCommerce is set to display them, computed for the
shop's base address. The module reads it from WooCommerce and never recomputes it.

A product has a price range: a variable product spans its variations, a grouped product its children. With a price
range alone, a product matches when its range **overlaps** the range asked for, which is the test WooCommerce itself
applies: a product sold from 28 to 62 matches a search for 40 to 70. Once a variation attribute is checked, a product
matches when one of its matching variants is priced within the range.

How prices are indexed, and when they must be reindexed, is in [Indexed prices](../indexing/prices.md).

## Bounds in the URL

The bounds travel as `min_price` and `max_price`:
`https://projet.ddev.site/shop/?min_price=20&max_price=80`.

These are the names WooCommerce reads, kept on purpose: a link written for WooCommerce drives the module without
translation. The module stops WooCommerce from filtering its own main query on them, but WooCommerce's widgets and
`is_filtered()` still see them. `php artisan meilifacets:check-parameters` therefore reports them as warnings, not
errors.

To give up that compatibility, rename them in `config/meilifacets.php`:

```php
'query_parameters' => [
    'min_price' => 'from',
    'max_price' => 'to',
],
```

A renamed bound is checked like any other parameter, and links written with the old names stop working.

## The “On sale” sort

The default sorts include “On sale”, which lists the products on sale. It is only offered by a listing that declares a
price filter, and it is hidden while no matching product is on sale, unless it is the sort in force. See
[Results, sorting and pagination](results-sort-pagination.md#sorting).

## Overriding the three sub-views

`<x-meilifacets::listing.price>` assembles three views, each overridable on its own under
`<theme>/resources/views/modules/meilifacets/components/listing/price/`:

| View | Rendered when | Receives |
| --- | --- | --- |
| `range.blade.php` | `PricePart::Slider` is declared | `label`, `readout`, `fill`, `handles`, `bounds`, `money` |
| `fields.blade.php` | `PricePart::Fields` is declared | `handles`, `bounds`, `money` |
| `hidden.blade.php` | `PricePart::Fields` is not declared | `handles` |

What they receive:

| Variable | Type | Content |
| --- | --- | --- |
| `label` | `string` | the filter's label |
| `readout` | `string` | the selected range, formatted: `€20.00 – €80.00` |
| `fill` | `Fill` | `from` and `to`, two ratios from 0 to 1: the selected part of the track |
| `handles` | `list<RangeHandle>` | the low handle, then the high one |
| `bounds` | `Range` | `min` and `max` of the track, `null` when there is no price |
| `money` | `Money` | `of(?float)` formats an amount as the shop does, `symbol()` returns the currency symbol |

A `RangeHandle` carries `bound` (a `PriceBound` case: `value` is `min` or `max`, `label()` reads From or To,
`handleLabel()` reads Lowest price or Highest price), `parameter` (the URL parameter name), `value`, `shown` (the
value to write in a field, `null` for an open end), `floor`, `ceiling`, `at` (the position on the track, 0 to 1) and
`written()` (the value, formatted).

These are anonymous components: each one starts with `@props` and receives only what it declares, not the variables
of the parent component. Write a hook with its enum case, not with `$hook()`:

```blade
@use(Modules\MeiliFacets\Enums\Hook)
@props(['handles', 'bounds', 'money'])
<div class="price-fields">
    @foreach ($handles as $handle)
        <label>
            {{ $handle->bound->label() }}
            <input type="number" inputmode="numeric" step="any"
                   name="{{ $handle->parameter }}" value="{{ $handle->shown }}"
                   min="{{ $bounds->min }}" max="{{ $bounds->max }}"
                   {{ $handle->bound->hook()->attribute() }}>
        </label>
    @endforeach
</div>
```

`$handle->bound->hook()` returns `Hook::PriceMin` or `Hook::PriceMax`; the slider uses `Hook::PriceRange`,
`Hook::PriceTrack` and `Hook::PriceHandle`. The full list is in [`data-meili` hooks](../reference/hooks.md).

A slider may draw a single handle. The missing one then counts as the edge of the track, so nothing is filtered on
that side: a slider with only the low handle filters `min_price` alone.

## Watch out

- **The block hides itself** when every matching product has the same price. Check the catalogue before suspecting the
  template.
- **The client handles the pointer and the keyboard**, not the view: an override keeps the hooks and the
  `role="slider"` attributes, and the client does the rest.
- **One price filter per listing.**
- **Prices change in the index only when products are reindexed.** A tax setting changed in WooCommerce needs a full
  reindex. See [Indexed prices](../indexing/prices.md).

## See also

- [Facets](facets.md), [Results, sorting and pagination](results-sort-pagination.md)
- [Indexed prices](../indexing/prices.md), [Overriding views](../customising/views.md)
- [`data-meili` hooks](../reference/hooks.md), [Blade components](../reference/components.md)
