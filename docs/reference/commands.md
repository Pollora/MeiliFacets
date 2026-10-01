# Commands

The Artisan commands the module ships, the Laravel, Pollora and WordPress commands it relies on, and the
development scripts of its repository.

You want to…

- [check URL parameters or published assets](#module-commands);
- [publish the configuration or the assets](#publishing);
- [index or reindex](#wordpress-cli);
- [run the module's own checks](#development).

```bash
php artisan module:publish MeiliFacets
php artisan meilifacets:check-assets
php artisan meilifacets:check-parameters
wp meiliscout index
```

## Module commands

### `meilifacets:check-parameters`

Checks every listing URL parameter (the mapped taxonomy names and the five reserved names, renamed or not)
against the names WordPress, WooCommerce and a reverse proxy already use. No options.

| Output | Level | Meaning |
| --- | --- | --- |
| `"<name>" is read from $_GET by WooCommerce: its query, its widgets or is_filtered() act on it.` | error | a parameter named `orderby`, `rating_filter` or `filter_*`, or named `min_price` / `max_price` without being that bound |
| `"<name>" is a public WordPress query var: WordPress would filter its own query in parallel.` | error | a public query var, as filtered by `query_vars` |
| `"<name>" is stripped by Varnish when it comes first: the whole query string would be dropped.` | error | `utm_campaign`, `utm_medium`, `utm_source`, `utm_term`, `adParams`, `client`, `cx`, `eid`, `fbid`, `feed`, `ref`, `refid`, `refsrc`, `ver`, `view` |
| `Rename them in config/meilifacets.php, under url_parameters or query_parameters.` | line | follows the errors |
| `"min_price" is read from $_GET by WooCommerce: …` and the same for `max_price` | warning | the price bounds keep WooCommerce's names on purpose |
| `Taken on purpose: the bounds keep the names WooCommerce reads, so a link written` / `for it drives the module. Rename them under query_parameters to give that up.` | line | follows the warnings |
| `No blocking conflict. <n> parameters checked.` | info | success |

| Exit code | When |
| --- | --- |
| `0` | no error; warnings allowed |
| `1` | at least one error |

See [How a listing works](../listing/README.md) and [Price filter](../listing/price.md).

### `meilifacets:check-assets`

Compares every file of the module's `resources/assets` with its copy under `public/modules/meilifacets`. No
options.

| Output | Level | Meaning |
| --- | --- | --- |
| `Published assets are up to date.` | info | every copy exists and is not older |
| `"<path>" is missing or older than the module's own copy.` | error | one line per file |
| `Run php artisan module:publish MeiliFacets, then clear the page cache.` | line | follows the errors |

| Exit code | When |
| --- | --- |
| `0` | up to date |
| `1` | at least one file missing or stale |

Run it in a deployment after publishing. See [Going to production](../production.md).

## Publishing

| Command | Effect | When |
| --- | --- | --- |
| `php artisan module:publish MeiliFacets` | copies the module's whole `resources/assets` folder (stylesheets, bundles, images) to `public/modules/meilifacets`, overwriting | on every deployment and every update of the module |
| `php artisan vendor:publish --tag=meilifacets-assets` | same copy, but skips files that already exist; add `--force` to overwrite | prefer `module:publish` |
| `php artisan vendor:publish --tag=meilifacets-config` | copies the commented starting point to `config/meilifacets.php`; skips an existing file | once, at installation |
| `php artisan module:publish-config MeiliFacets` | do not use: with `--force` it replaces `config/meilifacets.php` by the module's internal `config/config.php` | never |
| `php artisan discovery:clear` | clears Pollora's discovery cache | after adding a `Listing` class or updating the module |

See [Installation](../installation.md).

## WordPress CLI

Provided by MeiliScout, WordPress and WooCommerce. Every indexing run also writes the index settings.

| Command | Options | Effect |
| --- | --- | --- |
| `wp meiliscout index` | `--clear`: empty the indices first. `--purge`: empty them without reindexing. `--chunk-size=<size>`: index in chunks, each in its own process | indexes every indexed post type and writes the settings |
| `wp cron event run --due-now` | | runs due WP-Cron events, MeiliScout's deferred indexing included |
| `wp action-scheduler run` | | runs WooCommerce's scheduled actions, scheduled sales included, which change prices |

Reindex after changing taxes, `card.image_size`, `engine.*`, `displayed_attributes`, a projector or the search
order. See [What gets indexed](../indexing/README.md) and [Indexed prices](../indexing/prices.md).

## Development

Run from the module's repository.

| Command | Runs |
| --- | --- |
| `composer check` | `lint`, `lint:js`, `types:js`, `refactor`, `test`, `test:js`, `build:check` |
| `composer lint` | Pint, in test mode |
| `composer fix` | Pint |
| `composer refactor` | Rector, dry run |
| `composer test` | PHPUnit, `Unit` suite |
| `composer test:host` | PHPUnit, `Feature` suite (needs a host application) |
| `composer lint:js` / `npm run lint` | ESLint on `bundle.ts`, `resources/assets/ts` and `tests/ts` |
| `composer types:js` / `npm run typecheck` | TypeScript type check, sources and tests |
| `composer test:js` / `npm test` | Node's test runner on `tests/ts`, with coverage thresholds |
| `composer build` / `npm run build` | builds `resources/assets/dist` |
| `composer build:check` / `npm run build:check` | checks the built bundles are up to date |

## Watch out

- `vendor:publish --tag=meilifacets-assets` without `--force` says nothing when it skips a file: an update
  is left unpublished. Use `module:publish`.
- A page cache keeps serving the old asset URLs after a publish: clear it.

## See also

- [Installation](../installation.md)
- [Going to production](../production.md)
- [Errors and console messages](errors.md)
