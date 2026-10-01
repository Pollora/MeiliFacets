# MeiliFacets

Faceted search for Pollora projects, powered by Meilisearch. The server renders the first page
with the filters in the URL already applied; every filter, sort and page change after that is a
single request from the browser to Meilisearch, with no WordPress in the loop.

## Why

A filtered WordPress listing is a full page load for every click. Measured locally on a
76-product catalogue: the whole search, facet counts included, takes 3 ms in the engine; the page
around it takes about 350 ms. Moving the listing to the engine turns each filter into the first
number instead of the second — while WordPress keeps the URLs, the routing and the SEO.

## Principles

- **The browser talks to Meilisearch directly.** No PHP proxy and no fallback. The search key is
  therefore public, so the index only lets it read what a card needs: `ID` and `card`.
- **The URL is the state.** WooCommerce's own paths are kept, filters travel as query vars, and the
  server applies them on first render. A filtered, sorted or paginated view is served
  `noindex, follow`, without a canonical.
- **Behaviour in the module, appearance in the theme.** Every view is overridable. The only thing a
  theme must keep is the set of `data-meili` hooks the client binds to — never a class.
- **MeiliScout indexes, MeiliFacets queries.** The module adds its fields — `facets`, `card`,
  `price` — to MeiliScout's documents through a filter, and declares its index settings through an
  extended indexable.
- **What changes from one shop to the next sits behind a contract with a default.** A project with
  nothing to declare gets a working product listing; a project that binds its own wins.
- **Prices are indexed as the shop displays them**, taxes included or not, at the shop's address.
  The module reads them through WooCommerce and never recomputes one.

## Extension points

Container bindings, set in a project service provider's `register()`.

| Contract | Default | Decides |
| --- | --- | --- |
| `ProductFacets` | category and brand | the taxonomies a shop is browsed by, their labels, order and limits |
| `ProductSorts` | price ↑↓, newest, and on sale when the facets declare a price filter | the sorts on offer |
| `CardProjector` | title, link, image, WooCommerce price | what a document carries to paint a card |
| `IndexAttributes` | WooCommerce price and stock fields | the attributes the index declares filterable, sortable and readable, and the ones matched without typos |
| `SearchableAttributes` | title, brand, category and SKU, other visible labels, excerpt, content | the searchable fields, most important first |
| `FacetCounter` | disjunctive `multi-search` | how facet counts are computed on first render |
| `SearchEngine` | Meilisearch client | how searches are sent |
| `Listing` | the product listing, when WooCommerce is active | what a listing declares — discovered, never registered |

```php
$this->app->scoped(ProductFacets::class, ShopFacets::class);
```

| Filter | Passed | Purpose |
| --- | --- | --- |
| `meilifacets/script_fetchpriority` | `string` | Load the client at another priority than `low`. |

Settings — browser connection, URL parameter names, image size, readable fields, when a search
fires — live in the project's `config/meilifacets.php`. Each one, and what cannot be configured, is
in [docs/configuration.md](docs/configuration.md).

## Requirements

- PHP 8.4 or later
- Pollora on Laravel 12 or 13
- [MeiliScout](https://github.com/AmphiBee/meiliscout), branch `feat/meilifacets` until it is merged
- A Meilisearch server above 1.10.3 — the exact minimum has not been measured
- WooCommerce 9.8 or later for the product listing and its prices; the module runs without it
- `ext-intl`, suggested: without it, facet values ordered by name fall back to a byte comparison

Developed against WordPress 6.9.5, WooCommerce 11.0.1 and Meilisearch 1.53.1.

## Installation

The module is not published as a package yet. It sits in `Modules/MeiliFacets/`, is enabled in
`modules_statuses.json`, and the project's `merge-plugin` merges its `composer.json`, MeiliScout
included. [docs/installation.md](docs/installation.md) has the step-by-step.

MeiliScout indexes and serves the first render from its own environment; the index is always
called `posts`:

```dotenv
MEILI_HOST=http://meilisearch:7700
MEILI_KEY=<master key, for indexing>
MEILI_SEARCH_KEY=<search-only key>
```

The module's settings live in the project's `config/meilifacets.php`. Publish the commented
starting point, which holds every key with its default:

```bash
php artisan vendor:publish --tag=meilifacets-config
```

Two of its keys are required: the address and the key the browser uses. The module never reads
them from the environment — the published file bridges them:

```php
'browser' => [
    'url' => env('MEILI_PUBLIC_URL'),   // with its scheme, or the client stays off
    'key' => env('MEILI_SEARCH_KEY'),   // search-only, never the master key
],
```

Without them the page is still served in full, but no script loads and no filter answers — and
nothing says so. The two addresses differ on purpose: one is reached by PHP, the other by the
visitor's browser. The other keys — URL parameter names, image size, readable fields, when a
search fires — are described in [docs/configuration.md](docs/configuration.md).

The stylesheet and the client are copied into `public/` on every deployment:

```bash
php artisan module:publish MeiliFacets
```

## Usage

On a WooCommerce project there is nothing to declare: the module provides the product listing.
Components go anywhere in the template and share a single search.

```blade
<x-meilifacets::listing>
    <x-meilifacets::listing.active-filters />
    <x-meilifacets::listing.sort />
    <x-meilifacets::listing.reset />
    <x-meilifacets::listing.facets />
    <x-meilifacets::listing.results />
    <x-meilifacets::listing.pagination />
</x-meilifacets::listing>
```

Any other content gets a listing by implementing `Listing`; the class is discovered on its own.

### Declaring facets

Without a binding, a shop is browsed by category and brand. A project that wants other facets
implements `ProductFacets` and lists them in the order the page shows them:

```php
use Modules\MeiliFacets\Contracts\ProductFacets;
use Modules\MeiliFacets\Enums\DisplayOrder;
use Modules\MeiliFacets\Enums\Presentation;
use Modules\MeiliFacets\Enums\ProductTaxonomy;
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
            new ChildTermsFacet(ProductTaxonomy::Category->value, __('Category'), order: $this->names),
            new Facet(ProductTaxonomy::Brand->value, __('Brand'), order: $this->names),
            new Facet('pa_color', __('Color'), order: DisplayOrder::Declared, presentation: Presentation::Pill),
            new PriceFilter(__('Price')),
        ];
    }
}
```

```php
// in a project service provider's register()
$this->app->scoped(ProductFacets::class, ShopFacets::class);
```

A `Facet` takes a taxonomy and a label; the named arguments are optional — `selection`
(`SelectionMode::Multiple` or `Single`), `order` (`DisplayOrder::Count`, `Declared`, or a
`ValueOrder` such as `NameOrder`), `visible`, `cap`, `defaultTerm`, `name` and `presentation`.
`ChildTermsFacet` offers only the terms directly under the one the path carries, which keeps a deep
hierarchy readable. Declaring a `PriceFilter` also enables the "On sale" sort. The module binds its
default with `scopedIf`, so the project's `scoped` wins.
[docs/configuration.md](docs/configuration.md) covers naming a facet, placing it on its own in a
template, and presentations of your own.

### Overriding behaviour

Every other contract in the table above is replaced the same way, and most are best **decorated**:
take the module's default in the constructor and change what it returns, so later additions still
reach the project. A sort menu without "New arrivals":

```php
use Illuminate\Support\Arr;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Listing\WooCommerceSorts;

final class ShopSorts implements ProductSorts
{
    public function __construct(private readonly WooCommerceSorts $defaults) {}

    public function all(): array
    {
        return Arr::except($this->defaults->all(), ['newest']);
    }
}
```

A card carrying one more field stacks on the existing projector with `extend()` — a `bind()` would
drop the WooCommerce price:

```php
use Modules\MeiliFacets\Contracts\CardProjector;
use WP_Post;

final readonly class SubtitleCardProjector implements CardProjector
{
    public function __construct(private CardProjector $card) {}

    public function project(WP_Post $post): array
    {
        return [
            ...$this->card->project($post),
            'subtitle' => (string) get_post_meta($post->ID, 'subtitle', true),
        ];
    }
}
```

```php
$this->app->scoped(ProductSorts::class, ShopSorts::class);
$this->app->extend(
    CardProjector::class,
    fn (CardProjector $card): CardProjector => new SubtitleCardProjector($card)
);
```

A new card field is only in the documents after a reindex. Searchable fields and their rank
(`SearchableAttributes`) and the content types the site search queries (`SearchableTypes`) are
decorated the same way; [docs/configuration.md](docs/configuration.md) has an example of each, and
how to bind a projected field in a card view.

### Overriding the markup

The module looks for its views in the active theme first. A file in
`<theme>/resources/views/modules/meilifacets/components/` replaces one view, under the same path as the
module's (`listing/facet.blade.php`, `search/card.blade.php`); the others keep following the module's updates.

Tags, classes and styles belong to the theme. The `data-meili` hooks do not: when a required hook
is missing, or the contract version on the listing root no longer matches the client's, the client
does not start — the page stays as the server rendered it, and the console names what is missing.
The hooks are listed in [docs/architecture.md](docs/architecture.md).

### Theming

The stylesheets are neutral: colours derive from `currentColor` and the system colours `Canvas` and
`CanvasText`, and no font is set. Appearance is adjusted with CSS custom properties. Their
defaults are declared on the component roots — `[data-listing]` for the listing,
`[data-meili="search"]` for the site search — so a value set on `:root` never reaches them: set it
on those roots. A stylesheet requested after `wp_head` is printed in the body, just before its
component and after the theme's, so give the override a selector more specific than the module's:

```css
body [data-listing],
body [data-meili="search"] {
    --meili-radius: 4px;
    --meili-surface: #ffffff;
    --meili-control: 2.75rem;
    --meili-duration-hover: 100ms;
}
```

| Variable | Root | Sets |
| --- | --- | --- |
| `--meili-edge` | both | border colour of fields and controls |
| `--meili-rule` | both | separator lines |
| `--meili-tint` | both | hover background |
| `--meili-muted` | both | secondary text: counts, prices, summaries |
| `--meili-scrim` | both | veil behind the drawer or the search panel |
| `--meili-surface` | both | background of what sits over the page: panels, drawer, sort list |
| `--meili-line` | listing | line height of a section heading and of a value |
| `--meili-radius` | both | corner radius of controls, value rows and thumbnails |
| `--meili-pill` | both | corner radius of pill-shaped controls |
| `--meili-ui` | both | text size of the components |
| `--meili-control` | both | height of controls and fields |
| `--meili-control-inline` | listing | inline padding of the bar's controls |
| `--meili-ease` | both | easing of entrances |
| `--meili-ease-drawer` | listing | easing of the mobile drawer |
| `--meili-ease-exit` | search | easing of the panel and veil leaving |
| `--meili-duration-hover` | both | hover transitions |
| `--meili-duration-fade` | both | fades |
| `--meili-duration-drawer-in`, `--meili-duration-drawer-out` | listing | drawer opening and closing |
| `--meili-duration-pop-in`, `--meili-duration-pop-out` | search | panel opening and closing |
| `--meili-layer-drawer` | listing | `z-index` of the drawer |
| `--meili-layer-search` | search | `z-index` of the search panel; its veil sits one below |

Spacing, panel widths and the search icons have their own variables; the full list, with each
default, is in [docs/configuration.md](docs/configuration.md). A theme that wants none of this
dequeues the stylesheets (`meilifacets`, `meilifacets-site-search`) and styles the `data-meili`
hooks itself. Markup is overridden by view, as described above, under
`<theme>/resources/views/modules/meilifacets/components/`.

### Before going to production

- `php artisan meilifacets:check-parameters` checks the URL parameter names against WordPress
  query vars, the names WooCommerce reads, and what the proxy strips.
- The production engine must be upgraded past 1.10.3.
- Reindex after regenerating thumbnails or editing an image in the media library: a card's image
  URL, `srcset`, alt text and dimensions are stored at indexing time, and editing the image alone
  does not reindex the products that use it.
- WP-Cron has to run, or the index drifts from the catalogue without a sign
  ([docs/configuration.md](docs/configuration.md)).

## Development

Run from the module's root, on the host — not in ddev. A `node_modules` only serves the system that
installed it, and the module keeps one.

```bash
npm install
composer install

composer check   # Pint, ESLint, tsc, Rector, standalone PHP tests, client tests, bundle up to date
composer test    # standalone PHP tests only (Unit suite)
npm test         # client tests only
composer build   # rebuild resources/assets/dist/listing.js after changing the client
```

The `Feature` tests render Blade views and need an application, so they run from the host
project:

```bash
ddev exec vendor/bin/phpunit --testsuite Modules
```

Requires Node 24.12 or later.

## Documentation

The design documents, in French, are in [`docs/`](docs):
[installation](docs/installation.md), [configuration](docs/configuration.md),
[architecture](docs/architecture.md), [price filter](docs/prix.md),
[decisions](docs/decisions.md), [known pitfalls](docs/pieges.md),
[delivery batches](docs/lots.md) and the [review register](docs/revue.md), where every finding,
question and decision has a stable number.

## Contributing

Commits follow [Conventional Commits](https://www.conventionalcommits.org/), on a single line.
Before opening a pull request, `composer check` and the `Modules` suite should both pass. The
working rules — what to check before writing, and when a point is done — are in
[CLAUDE.md](CLAUDE.md).

No release has been tagged yet.

## License

GPL-2.0-or-later.
