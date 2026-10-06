# MeiliFacets

Faceted product listings and a site-wide search panel for [Pollora](https://pollora.dev) projects, served by
[Meilisearch](https://www.meilisearch.com) straight from the visitor's browser.

You want to…

- [know what the module does](#what-it-is);
- [understand how a page is served](#how-it-works);
- [know what you get without writing PHP](#what-you-get-without-writing-php);
- [check that it fits your stack](#requirements);
- [know the rules the module is built on](#design-principles);
- [find the right page](#documentation);
- [work on the module itself](#development).

```blade
<x-meilifacets::listing>
    <x-meilifacets::listing.active-values />
    <x-meilifacets::listing.sort />
    <x-meilifacets::listing.reset />
    <x-meilifacets::listing.facets />
    <x-meilifacets::listing.results />
    <x-meilifacets::listing.pagination />
</x-meilifacets::listing>
```

```blade
<header class="site-header">
    {{-- logo, menu… --}}
    <x-meilifacets::search />
</header>
```

## What it is

Two features, both served by Meilisearch:

- **Listing filters.** Facets by taxonomy or product attribute, a price range, sorting, pagination, active filters,
  a search field that narrows the list, and a mobile drawer.
- **Site search.** A magnifier in the header opens a panel with one section per content type: products, posts, pages
  and any indexed custom post type, with a result count and a link to the full results.

What it does not do: it does not index anything itself, and it does not replace WordPress's routing or templates.
[MeiliScout](https://github.com/AmphiBee/meiliscout), a WordPress plugin, builds and pushes the documents.

## How it works

```text
first render          browser ──► WordPress / Pollora ──► Meilisearch      filters of the URL applied by PHP
every later gesture   browser ─────────────────────────► Meilisearch      one request, no WordPress in the loop
indexing              WordPress ──► MeiliScout ────────► Meilisearch      MeiliFacets adds its fields to each document
```

- The server renders the first page with the filters of the URL already applied. The page is complete without
  JavaScript, and search engines see real content.
- After that, every check, sort, page or search term is a single request from the browser to Meilisearch. The client
  rewrites the address bar, so a filtered view can be bookmarked and shared.
- The engine computes the results and the facet counts in a few milliseconds. A full WordPress page load is avoided
  on every click, while WordPress keeps the URLs, the routing and the SEO.
- **MeiliScout indexes, MeiliFacets queries.** The module adds its own fields (`facets`, `card`, `price`…) to the
  documents MeiliScout builds, and declares the index settings it needs.

## What you get without writing PHP

On a WooCommerce project, with MeiliScout indexing products:

- a product listing named `products`, filtered by category and brand;
- sorting by relevance, price low to high, price high to low and newest; « On sale » is added when you declare a
  price filter;
- cards with the image, title, link and the price as the shop displays it, taxes included or not;
- a site search over every public post type MeiliScout indexes, products first.

Everything else (other facets, the price filter, your own card, other sorts) is a configuration key, a view override
or a container binding. See [Quick start](docs/quick-start.md).

## Requirements

| | |
| --- | --- |
| PHP | 8.4 or later |
| Framework | Pollora 13.4 or later, on Laravel 13. Laravel modules (`nwidart/laravel-modules`) ship with Pollora |
| Indexing | MeiliScout (`amphibee/meiliscout`), a WordPress plugin, currently its `dev-feat/meilifacets` branch |
| Engine | a Meilisearch server |
| Products | WooCommerce, for the product listing and its prices. The site search works without it |
| Optional | `ext-intl`, so that facet values ordered by name follow the site's language |

The details, and the step-by-step setup, are in [Installation](docs/installation.md).

## Design principles

- **The URL is the state.** WordPress's own paths are kept, filters travel as query parameters, and the server
  applies them on first render.
- **Behaviour in the module, appearance in the theme.** Every view can be overridden from the theme. The stylesheets
  are neutral and tuned with CSS custom properties.
- **Hooks, not classes.** The browser client binds to `data-meili` attributes, never to a class name. A theme
  changes tags and classes freely as long as it keeps the hooks.
- **Every shop-specific choice is a contract with a default.** Facets, sorts, the card, the searched fields and the
  searched types each sit behind a PHP interface. A project with nothing to declare gets a working listing; a project
  that binds its own implementation wins.
- **The browser key is public, so the index exposes little.** The search key travels to every visitor. The index
  only lets it read `ID`, `card` and `parent_id`, and it must only hold published content. See
  [Going to production](docs/production.md#security).

## Documentation

### Reading this documentation

- Commands are written without a container runner: `php artisan …`, `wp meiliscout index`, `composer …`. Prefix them
  with yours when PHP runs in a container (`ddev exec php artisan …`, `ddev wp …`, `docker compose exec …`).
- `<theme>` stands for the active theme's folder. A view override lives under
  `<theme>/resources/views/modules/meilifacets/…`.
- Component tags are written with their full prefix: `<x-meilifacets::listing.facet>`.

### Map

| Section | Pages |
| --- | --- |
| Getting started | [Installation](docs/installation.md) · [Going to production](docs/production.md) · [Quick start](docs/quick-start.md) |
| Listing and filters | [How a listing works](docs/listing/README.md) · [Facets](docs/listing/facets.md) · [Price filter](docs/listing/price.md) · [Results, sorting and pagination](docs/listing/results-sort-pagination.md) · [Mobile drawer and filter bar](docs/listing/drawer.md) · [Listing other content](docs/listing/custom-listing.md) |
| Site search | [Site search](docs/search/README.md) · [Composing the search panel](docs/search/composition.md) · [Searchable content types](docs/search/types.md) |
| Relevance and indexing | [What gets indexed](docs/indexing/README.md) · [Search relevance](docs/indexing/relevance.md) · [Indexed prices (WooCommerce)](docs/indexing/prices.md) |
| Customising | [Overriding views](docs/customising/views.md) · [Your own card](docs/customising/card.md) · [Styles and design tokens](docs/customising/styles.md) · [PHP extension points](docs/customising/php.md) · [Translating the interface](docs/customising/translations.md) |
| Accessibility | [Accessibility and motion](docs/accessibility.md) |
| Reference | [Overview](docs/reference/README.md) · [Blade components](docs/reference/components.md) · [`data-meili` hooks](docs/reference/hooks.md) · [CSS custom properties](docs/reference/css-tokens.md) · [Configuration and environment](docs/reference/configuration.md) · [PHP contracts](docs/reference/contracts.md) · [WordPress filters and actions](docs/reference/wordpress-hooks.md) · [Index settings and document fields](docs/reference/index-settings.md) · [Errors and console messages](docs/reference/errors.md) · [Commands](docs/reference/commands.md) |
| When something goes wrong | [Troubleshooting and FAQ](docs/troubleshooting.md) |
| Releases | [Changelog](CHANGELOG.md) · [Upgrading](docs/upgrading.md) |

The maintainers' design notes, in French, are in [`docs/internal/`](docs/internal): decisions, measurements and known
traps gathered while building the module. They are not needed to use it.

## Development

Run these from a clone of the module's repository, not from a copy installed in a project. The repository keeps its
own `node_modules`, which only serves the system that installed it. Node 24.12 or later is required.

```bash
npm install
composer install

composer check   # Pint, ESLint, tsc, Rector, standalone PHP tests, client tests, bundle up to date
composer test    # standalone PHP tests only (Unit suite)
npm test         # client tests only
composer build   # rebuild resources/assets/dist after changing the client
```

The `Feature` tests render Blade views and need a host application. Run them from a Pollora project where the module
is installed, with a PHPUnit suite that includes `Modules/MeiliFacets/tests/Feature`.

Commits follow [Conventional Commits](https://www.conventionalcommits.org/). Before opening a pull request,
`composer check` and the `Feature` tests should both pass. The current release is 0.1.0, a beta: see the
[changelog](CHANGELOG.md).

## License

GPL-2.0-or-later.
