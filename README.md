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
| `IndexAttributes` | WooCommerce price and stock fields | the attributes the index declares filterable, sortable and readable |
| `FacetCounter` | disjunctive `multi-search` | how facet counts are computed on first render |
| `SearchEngine` | Meilisearch client | how searches are sent |
| `Listing` | the product listing, when WooCommerce is active | what a listing declares — discovered, never registered |

```php
$this->app->scoped(ProductFacets::class, CatalogueFacets::class);
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
    <x-meilifacets::active-filters />
    <x-meilifacets::sort />
    <x-meilifacets::reset />
    <x-meilifacets::facets />
    <x-meilifacets::results />
    <x-meilifacets::pagination />
</x-meilifacets::listing>
```

Any other content gets a listing by implementing `Listing`; the class is discovered on its own.

### Overriding the markup

The module looks for its views in the active theme first. A file in
`<theme>/resources/views/modules/meilifacets/components/` replaces one view; the others keep
following the module's updates.

Tags, classes and styles belong to the theme. The `data-meili` hooks do not: when a required hook
is missing, or the contract version on the listing root no longer matches the client's, the client
does not start — the page stays as the server rendered it, and the console names what is missing.
The hooks are listed in [docs/architecture.md](docs/architecture.md).

### Before going to production

- `php artisan meilifacets:check-parameters` checks the URL parameter names against WordPress
  query vars, the names WooCommerce reads, and what the proxy strips.
- The production engine must be upgraded past 1.10.3.
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
