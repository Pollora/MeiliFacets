# Quick start

This page takes a WooCommerce project where the module is installed to a filtered shop page, a search in the header
and a first customisation, in a few minutes. It assumes [Installation](installation.md) is done and the products are
indexed.

You want to…

- [put a filtered product listing on the shop page](#a-product-listing-on-the-shop-page);
- [give the URL parameters readable names](#readable-parameter-names);
- [add the site search to the header](#the-site-search-in-the-header);
- [make it look like your theme](#make-it-look-like-your-theme);
- [declare your own facets](#your-own-facets);
- [know where to go next](#where-to-go-next).

## A product listing on the shop page

Pollora renders WooCommerce templates from Blade views of the theme. The shop and its category archives use
`archive-product`:

```blade
{{-- <theme>/resources/views/woocommerce/archive-product.blade.php --}}
@extends('layouts.app')

@section('content')
    <h1>{{ woocommerce_page_title(false) }}</h1>

    <x-meilifacets::listing>
        <x-meilifacets::listing.active-values />
        <x-meilifacets::listing.sort />
        <x-meilifacets::listing.reset />
        <x-meilifacets::listing.facets />
        <x-meilifacets::listing.results />
        <x-meilifacets::listing.pagination />
    </x-meilifacets::listing>
@endsection
```

Use your theme's own layout in place of `layouts.app`. The layout must call `wp_footer()`: the data the client reads
is printed there.

Nothing is declared in PHP: on a WooCommerce project, the module provides a product listing filtered by category and
brand. Open the shop, check a brand, and the address becomes:

```text
https://projet.ddev.site/shop/?f_product_brand=acme
```

The results, the counts and the address change without a page load. Reload: the server renders the same filtered
view. Components can sit in any markup of your template, as long as they stay inside `<x-meilifacets::listing>`. See
[How a listing works](listing/README.md).

## Readable parameter names

A taxonomy travels as `f_<taxonomy>` until you name it. In `config/meilifacets.php`:

```php
'url_parameters' => [
    'product_cat' => 'category',
    'product_brand' => 'brand',
],
```

The address becomes `https://projet.ddev.site/shop/?brand=acme`. Check the names before going live, since renaming
breaks the links already shared:

```bash
php artisan meilifacets:check-parameters
```

## The site search in the header

Place the search root in the header. The panel hangs under the first positioned ancestor, so position the header bar:

```blade
<header class="site-header">
    {{-- logo, menu… --}}
    <x-meilifacets::search />
</header>
```

```css
.site-header {
    position: relative;
}
```

A magnifier appears; it opens a panel with one section per indexed content type, products first. To use your own
icon, pass it in the `icon` slot:

```blade
<x-meilifacets::search>
    <x-slot:icon>
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><!-- … --></svg>
    </x-slot:icon>
</x-meilifacets::search>
```

See [Site search](search/README.md).

## Make it look like your theme

**Change a design token.** Tokens are declared on the component roots, so set them there, with a selector more
specific than the module's:

```css
body [data-listing],
body [data-meili="search"] {
    --meili-control: 2.75rem;
    --meili-radius: 4px;
}
```

**Override one view.** Copy the module's view into the theme under the same path, and change the markup. Here the
listing card shows the price above the title:

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

Tags and classes are yours; the attributes the module prepares (`$link->attributes`, `$image->attributes`…) carry the
`data-meili` hooks the client fills, and must stay. If the theme already has a card component, the override can hand
over to it, for example `<x-theme::shop-card :binding="$binding" :priority="$priority" />`: see
[Your own card](customising/card.md).

## Your own facets

To filter by colour as well, bind your own list of facets. Start from the module's defaults:

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

The `pa_color` attribute must be indexed: select it in MeiliScout's admin screen, then run `wp meiliscout index`. The
colour facet appears after the category and the brand, under `?f_pa_color=…`. See [Facets](listing/facets.md).

## Where to go next

- a price range: [Price filter](listing/price.md);
- other sorts, the total, active filters: [Results, sorting and pagination](listing/results-sort-pagination.md);
- filters in a drawer on small screens: [Mobile drawer and filter bar](listing/drawer.md);
- the sections of the search panel: [Composing the search panel](search/composition.md);
- the rest of the tokens and the views: [Styles and design tokens](customising/styles.md),
  [Overriding views](customising/views.md);
- before deploying: [Going to production](production.md).
