# Installation

This page installs MeiliFacets and MeiliScout in a Pollora project, connects PHP and the browser to Meilisearch,
indexes the content for the first time and checks that everything is wired.

You want to…

- [check the requirements](#requirements);
- [prepare the project's `composer.json`](#prepare-composerjson);
- [install the module](#install-the-module);
- [activate MeiliScout](#activate-meiliscout);
- [connect PHP and the browser to the engine](#two-addresses-for-one-engine);
- [publish the configuration file](#publish-the-configuration-file);
- [choose what is indexed and index it](#choose-what-is-indexed);
- [publish the stylesheets and the client](#publish-the-assets);
- [check that it works](#check-that-it-works);
- [update or remove the module](#updating).

The whole sequence, once `composer.json` is prepared:

```bash
composer require pollora/meilifacets:^0.2 amphibee/meiliscout:dev-feat/meilifacets -W
echo '{"MeiliFacets": true}' > modules_statuses.json   # or add the key to the existing file
wp plugin activate meiliscout
php artisan vendor:publish --tag=meilifacets-config
# set MEILI_HOST, MEILI_KEY, MEILI_SEARCH_KEY, MEILI_PUBLIC_URL in .env
# choose the indexed content in MeiliScout's admin screen
wp meiliscout index --clear
php artisan module:publish MeiliFacets
php artisan meilifacets:check-assets
```

## Requirements

| Requirement | Version | Notes |
| --- | --- | --- |
| PHP | 8.4 or later | |
| Pollora | 13.x from 13.35 (`pollora/framework` `>=13.35 <14`), on Laravel 13 | Laravel modules (`nwidart/laravel-modules`) ship with Pollora: nothing to install |
| MeiliScout | `amphibee/meiliscout`, branch `dev-feat/meilifacets` | a WordPress plugin; it builds and pushes the documents |
| Meilisearch | a server reachable from PHP and from the browser | see [Engine version](production.md#engine-version) |
| WooCommerce | for the product listing | 9.8 for grouped product prices, 9.9 to switch off WooCommerce's own filtering of the shop query. The site search runs without WooCommerce |
| `ext-intl` | suggested | without it, facet values ordered by name fall back to a byte comparison that misplaces accented labels |

## Prepare `composer.json`

The project's `composer.json` needs two settings under `extra`. New projects will get them from the Pollora skeleton;
until then, add them by hand. Keep the WordPress rules already under `installer-paths`, and add the `Modules/` rule
**last**:

```json
"extra": {
    "installer-paths": {
        "public/content/mu-plugins/{$name}/": ["type:wordpress-muplugin"],
        "public/content/plugins/{$name}/": ["type:wordpress-plugin"],
        "public/content/themes/{$name}/": ["type:wordpress-theme"],
        "Modules/{$name}/": ["vendor:pollora"]
    },
    "merge-plugin": {
        "include": ["Modules/*/composer.json"],
        "merge-dev": false
    }
}
```

- **`installer-paths`** places the package in `Modules/MeiliFacets/`. The module declares `type: laravel-library`
  and `installer-name: MeiliFacets`, and requires `composer/installers`, which reads them. The rule is limited to the
  `pollora` vendor, so other Laravel packages that declare `laravel-library` keep their usual place. Without it
  `composer/installers` falls back to its own default for that type, `libraries/MeiliFacets/`, and Pollora never
  finds it. **Order matters:** `composer/installers` applies the first rule that matches, and a `vendor:` rule ignores
  the package type, so placed first it would send Pollora's own WordPress plugins (`pollora/mcp-connector`,
  `pollora/ai-visibility`) to `Modules/` too. This is the order the Pollora skeleton uses.
- **`merge-plugin`** merges the module's `composer.json` into the project's, so its autoloading and dependencies
  reach the project.
- **`merge-dev: false` is required.** Without it, the merge plugin also merges the module's `require-dev`, and a
  later `composer install` removes WordPress core (`public/cms`).

## Install the module

```bash
composer require pollora/meilifacets:^0.2 amphibee/meiliscout:dev-feat/meilifacets -W
```

MeiliScout has no tagged release yet. While the module depends on a development branch, the project has to require
that branch itself: Composer only accepts a `dev-` version from a dependency of a dependency when the root package
asks for it, because of `minimum-stability`. MeiliScout declares `type: wordpress-plugin`, so it is installed with
the other plugins (`public/content/plugins/meiliscout`), not in `vendor/`.

Then enable the module in `modules_statuses.json`, at the project root. Create the file if it does not exist, or add
the key:

```json
{
    "MeiliFacets": true
}
```

Without this file, Laravel modules does not load the module and Pollora's attribute discovery skips it.

## Activate MeiliScout

```bash
wp plugin activate meiliscout
```

Activation is stored in the database: repeat it on every environment.

## Two addresses for one engine

The module needs **two** addresses for the same Meilisearch server. Mixing them up gives a listing that renders
correctly on first load, then fails on every filter.

| Who calls | Variable | Example, local | Example, production |
| --- | --- | --- | --- |
| PHP, from the web container: indexing and first render | `MEILI_HOST` | `http://meilisearch:7700` | the engine's internal address |
| The visitor's browser: every later gesture | `MEILI_PUBLIC_URL` | `https://projet.ddev.site:7701` | the engine's public address |

A web container does not see the port published on the host: from PHP, the address is the service name.

## Environment variables

In the project's `.env`:

```dotenv
MEILI_HOST=http://meilisearch:7700
MEILI_KEY=<key with write access, for indexing>
MEILI_SEARCH_KEY=<search-only key>
MEILI_PUBLIC_URL=https://projet.ddev.site:7701
```

| Variable | Read by | Purpose |
| --- | --- | --- |
| `MEILI_HOST` | MeiliScout | engine address reached by PHP |
| `MEILI_KEY` | MeiliScout | key used to index; never sent to the browser |
| `MEILI_SEARCH_KEY` | MeiliScout for the first render, the configuration file for the browser | search-only key; how to create one is in [Going to production](production.md#the-search-key) |
| `MEILI_PUBLIC_URL` | the configuration file | engine address reached by the browser, scheme included |

The index is always called `posts`: it is MeiliScout's post index, and its name is not configurable.

## Publish the configuration file

```bash
php artisan vendor:publish --tag=meilifacets-config
```

This copies a commented starting point to `config/meilifacets.php`, with every key and its default. The module never
reads the environment itself: this file is the bridge. Two keys are required, the address and the key the browser
uses:

```php
// config/meilifacets.php
'browser' => [
    'url' => env('MEILI_PUBLIC_URL'),   // with its scheme, or the client stays off
    'key' => env('MEILI_SEARCH_KEY'),   // search-only, never the master key
],
```

Without them, the page is still served in full, but no script loads and no filter answers, with no message. Every
other key is described in [Configuration and environment](reference/configuration.md).

## Choose what is indexed

In MeiliScout's admin screen, select the post types, the taxonomies and the meta keys to index. For a shop, index
`product`, its taxonomies (`product_cat`, `product_brand`, the `pa_*` attributes you filter on) and, if you set a list
of meta keys, keep `_price`, `_stock_status` and `_sku`: the module declares them filterable or searchable.

This selection lives in the database, not in the repository. Redo it on every environment, and write it down.

## First indexing

```bash
wp meiliscout index --clear
```

`--clear` empties the index first. For a large catalogue, `--chunk-size=<size>` indexes in chunks, each in its own
process. Every indexing run also writes the index settings the module needs (filterable and sortable fields, searched
fields, ceilings).

## Publish the assets

The module lives in `Modules/`, outside the web root. Its stylesheets and its browser client are copied into
`public/modules/meilifacets`:

```bash
php artisan module:publish MeiliFacets
php artisan meilifacets:check-assets
```

`meilifacets:check-assets` compares every file with its published copy and exits with code `1` when a copy is
missing or older. Run both commands on every deployment and after every update of the module; see
[Going to production](production.md#deployment-checklist).

## Check that it works

```bash
php artisan module:list                  # MeiliFacets is listed as Enabled
php artisan list | grep meilifacets      # meilifacets:check-assets, meilifacets:check-parameters
```

Then ask the engine whether it holds documents, from where PHP runs:

```bash
curl -s -H "Authorization: Bearer <MEILI_KEY>" http://meilisearch:7700/stats
```

`{"indexes":{}}` means the engine runs **without any data**. An empty engine answers `200 OK` to every search, so
this is the only place where it shows.

Finally, open the shop page with a listing on it (see [Quick start](quick-start.md)), check a box, and open the
browser console: a `[meilifacets]` line means the markup or the publication is wrong. See
[Errors and console messages](reference/errors.md#browser-console).

## Updating

```bash
composer update pollora/meilifacets
php artisan module:publish MeiliFacets
php artisan meilifacets:check-assets
```

`composer update` replaces the whole `Modules/MeiliFacets/` folder. **Never edit the installed module**: your changes
would be lost at the next update. Customise it through:

- the configuration file, `config/meilifacets.php`;
- view overrides in the theme, under `<theme>/resources/views/modules/meilifacets/…` (see
  [Overriding views](customising/views.md));
- container bindings in a project service provider (see [PHP extension points](customising/php.md));
- CSS custom properties in the theme (see [Styles and design tokens](customising/styles.md)).

Add the installed module to the project's `.gitignore`, like `vendor/`. `public/modules/` is published output too:

```text
/Modules/MeiliFacets/
/public/modules/
```

Read [Upgrading](upgrading.md) before moving to a new version.

## Removing

```bash
composer remove pollora/meilifacets
```

Three things are left in place, to remove by hand if you no longer want them: the `MeiliFacets` key in
`modules_statuses.json`, `config/meilifacets.php`, and the published assets in `public/modules/meilifacets`. Remove the
components from your templates first, or the pages that hold them fail to render.

## Watch out

- **`MEILI_PUBLIC_URL` needs its scheme.** `projet.ddev.site:7701` without `https://` switches the whole client off,
  without a message.
- **Never put the master key in `browser.key`.** It is printed in every page. See
  [Going to production](production.md#security).
- **`vendor:publish --tag=meilifacets-assets` without `--force` skips existing files silently.** Use
  `module:publish MeiliFacets`, which overwrites.
- **Do not run `php artisan module:publish-config MeiliFacets --force`.** It replaces your `config/meilifacets.php`
  with the module's internal configuration file, which holds none of your settings.
- **Open the local site over the scheme WordPress declares.** Assets are registered with the scheme of `home` and
  `siteurl`. A page opened over `http` when the site is declared in `https` sees its own scripts as another origin,
  and the browser refuses them, the theme's included.
- **MeiliScout's settings are not in the repository.** Indexed post types, taxonomies and meta keys are redone on
  every environment.

## See also

- [Quick start](quick-start.md)
- [Going to production](production.md)
- [Configuration and environment](reference/configuration.md)
- [Commands](reference/commands.md)
- [Troubleshooting and FAQ](troubleshooting.md)
