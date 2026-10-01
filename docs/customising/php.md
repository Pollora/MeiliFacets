# PHP extension points

This page covers how a project changes the module's behaviour from PHP: which contract or hook answers which need, and
how to bind your own implementation. Each contract is described in full in [PHP contracts](../reference/contracts.md).

You want to…

- [understand how the module's defaults are bound](#how-bindings-work-here);
- [find the extension point for a need](#recipes);
- [know what cannot be changed](#what-is-not-an-extension-point).

A shop that adds a colour facet to the default category and brand facets:

```php
<?php

declare(strict_types=1);

namespace App\Shop;

use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Enums\Presentation;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\WooCommerceFacets;

final readonly class ShopFacets implements ProductFacets
{
    public function __construct(private WooCommerceFacets $defaults) {}

    public function all(): array
    {
        return [
            ...$this->defaults->all(),
            new Facet('pa_color', __('Colour'), presentation: Presentation::Pill),
        ];
    }
}
```

```php
// In the register() method of one of your service providers
use App\Shop\ShopFacets;
use Modules\MeiliFacets\Contracts\ProductFacets;

$this->app->scoped(ProductFacets::class, ShopFacets::class);
```

## How bindings work here

The module binds a default for each contract in Laravel's service container. Most defaults are bound with `bindIf()`
or `scopedIf()`: they apply only when nothing else is bound. Your binding goes in the `register()` method of one of
your service providers.

| You want to | Use |
| --- | --- |
| replace a default | `$this->app->bind()` or `$this->app->scoped()`, the same lifetime as the module's binding |
| change what a default returns, and keep what it does | a class that takes the default in its constructor and changes its result (a decorator), bound as above |
| add a layer on top of a default that is itself a stack | `$this->app->extend()` |

- **Never bind with `bindIf()` or `scopedIf()` yourself.** Whether your provider registers before or after the module
  depends on the project: when the module comes first, your `…If()` binding is ignored without a word.
- **Prefer a decorator to a rewrite.** A class that receives the module's default and changes its result keeps
  following what the module adds later. The examples on this page all decorate.
- **Two contracts are bound unconditionally**: `IndexAttributes` and `SearchEngine`. A plain `bind()` from your side
  wins or loses depending on registration order. Use `extend()` for both: it applies whatever the order.
- **`CardProjector` is a stack**: a summary layer and a WooCommerce price layer around the base card. Use `extend()`;
  a `bind()` replaces the whole stack, price included.

## Recipes

| You want to | Extension point | How | Read |
| --- | --- | --- | --- |
| change the product facets | `ProductFacets` | `scoped()`, decorating `WooCommerceFacets` | [Facets](../listing/facets.md) |
| change the sort menu | `ProductSorts` | `scoped()`, decorating `WooCommerceSorts` | [Results, sorting and pagination](../listing/results-sort-pagination.md) |
| add fields to the card | `CardProjector` | `extend()` | [Your own card](card.md) |
| change which fields are searched, and their rank | `SearchableAttributes` | `scoped()`, decorating `DefaultSearchableAttributes` | [Search relevance](../indexing/relevance.md) |
| change the content types of the site search | `SearchableTypes` | `scoped()`, decorating `WooCommerceSearchableTypes` | [Searchable content types](../search/types.md) |
| change the site search defaults (characters, delay, results per section) | `SearchSettings` | `bind()` | [Site search](../search/README.md) |
| add filterable, sortable, readable or exactly matched fields | `IndexAttributes` | `extend()` | [What gets indexed](../indexing/README.md) |
| list another kind of content | `Listing` | a class discovered by the module | [Listing other content](../listing/custom-listing.md) |
| order a facet's values your way | `ValueOrder` | an object passed to the facet | [Facets](../listing/facets.md) |
| present a facet's values your way | `ValuePresentation` | an object passed to the facet | [Facets](../listing/facets.md) |
| count facets differently | `FacetCounter` | `bind()`, server render only | [PHP contracts](../reference/contracts.md) |
| wrap the engine client (cache, logging) | `SearchEngine` | `extend()`, server render only | [PHP contracts](../reference/contracts.md) |
| change the page size of a product listing | WooCommerce filter `loop_shop_per_page` | `add_filter()` | [Results, sorting and pagination](../listing/results-sort-pagination.md) |
| change the length of a card summary | WordPress filter `excerpt_length` | `add_filter()`, then reindex | [What gets indexed](../indexing/README.md) |
| change the load priority of the browser client | filter `meilifacets/script_fetchpriority` | `add_filter()` | [WordPress filters and actions](../reference/wordpress-hooks.md) |

### Removing a sort

```php
<?php

declare(strict_types=1);

namespace App\Shop;

use Illuminate\Support\Arr;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Listing\WooCommerceSorts;

final readonly class ShopSorts implements ProductSorts
{
    public function __construct(private WooCommerceSorts $defaults) {}

    public function all(): array
    {
        return Arr::except($this->defaults->all(), ['newest']);
    }
}
```

```php
$this->app->scoped(ProductSorts::class, ShopSorts::class);
```

The keys of `WooCommerceSorts` are `price_asc`, `price_desc`, `newest` and `on_sale`. They travel in the URL: renaming
one breaks the links that use it.

### Changing the site search defaults everywhere

```php
use Modules\MeiliFacets\SiteSearch\SearchSettings;

$this->app->bind(SearchSettings::class, fn (): SearchSettings => new SearchSettings(minChars: 3, limit: 6));
```

The named arguments change only what they name; the others keep their default (`minChars: 2`, `delay: 120`,
`limit: 4`). A single search root can still override them with its `min-chars` and `delay` attributes, and a section
with its `limit` attribute.

### Logging the server's searches

```php
<?php

declare(strict_types=1);

namespace App\Search;

use Illuminate\Support\Facades\Log;
use Modules\MeiliFacets\Contracts\SearchEngine;

final readonly class LoggedSearchEngine implements SearchEngine
{
    public function __construct(private SearchEngine $engine) {}

    public function multiSearch(array $queries): array
    {
        $started = hrtime(true);
        $answers = $this->engine->multiSearch($queries);

        Log::debug('MeiliFacets searched', [
            'queries' => array_keys($queries),
            'ms' => (hrtime(true) - $started) / 1e6,
        ]);

        return $answers;
    }
}
```

```php
$this->app->extend(
    SearchEngine::class,
    fn (SearchEngine $engine): SearchEngine => new LoggedSearchEngine($engine),
);
```

This wraps the searches the server sends to render a page. The searches a visitor's browser sends go straight to the
engine, with the public search key: no PHP sees them.

### Loading the browser client sooner

The browser client is loaded with `fetchpriority="low"`, because the page is already rendered by the server. To change
it:

```php
add_filter('meilifacets/script_fetchpriority', fn (string $priority, string $module): string =>
    $module === '@meilifacets/listing' ? 'auto' : $priority, 10, 2);
```

The second argument is `@meilifacets/listing` or `@meilifacets/site-search`. WordPress accepts `high`, `low` and
`auto`.

## What is not an extension point

- **The document field names**: `facets`, `card`, `price`, `terms`, `metas`, `labels`, `excerpt`, `content`. They are
  the contract between indexing and the browser client.
- **The field a facet filters on**: always `facets.<taxonomy>`, never a label an editor could rename.
- **Ancestor indexing**: a product is always indexed under the parents of its categories too. Turning it off would
  empty the parent category archives.
- **The order the engine returns facet values in** (`sortFacetValuesBy`): always by count, so a capped facet keeps its
  best-represented values. How a facet *displays* its values is set per facet.
- **« Relevance » in the sort menu**: always first, with an empty value, so a visitor can go back to the engine's
  order.
- **The browser's facet counting rule.** `FacetCounter` decides how the server counts the first page. From the
  visitor's first action, the browser counts with the module's own rule: a multiple-choice facet with a value checked
  is counted apart, the others on the main search. A counter that departs from this rule sees its counts replaced at
  the first click.
- **The browser client's request timeout**: 5 seconds.

## Watch out

- **Clear the discovery cache** after adding a class the module discovers, such as a `Listing`:
  `php artisan discovery:clear`.
- **Reindex after changing what is indexed**: `CardProjector`, `IndexAttributes`, `SearchableAttributes`, and the
  `excerpt_length` filter only take effect in the documents written after the change.
- **Translate labels with `__()` without a text domain**, as the module does: a facet label passed through
  `__('Colour')` is read from your Laravel JSON catalogue (see [Translating the interface](translations.md)).

## See also

- [PHP contracts](../reference/contracts.md)
- [WordPress filters and actions](../reference/wordpress-hooks.md)
- [Configuration and environment](../reference/configuration.md)
- [Your own card](card.md)
