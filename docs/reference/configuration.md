# Configuration and environment

Every setting the module reads, with its default in code, the environment variables involved, and what cannot be
configured.

You want to…

- [create the configuration file](#the-configuration-file);
- [look up a key and its default](#module-settings);
- [know which environment variables matter](#environment-variables);
- [know which MeiliScout settings the module depends on](#meiliscout-settings);
- [know what is not configurable](#not-configurable).

```bash
php artisan vendor:publish --tag=meilifacets-config
```

```php
// config/meilifacets.php
return [
    'browser' => [
        'url' => env('MEILI_PUBLIC_URL'),
        'key' => env('MEILI_SEARCH_KEY'),
    ],
    'apply_mode' => 'submit',
    'url_parameters' => [
        'product_cat' => 'category',
    ],
];
```

## The configuration file

The project owns `config/meilifacets.php`. The module reads each key with its default in code, so the file holds
only what you change. `vendor:publish --tag=meilifacets-config` copies a commented starting point,
`config/meilifacets.php.stub`, which lists every key below.

## Module settings

“Read” says when a change takes effect: **render** on the next page view, **indexing** at the next indexing of a
document (see [commands](commands.md#wordpress-cli)).

| Key | Type | Default in code | Default in the stub | Read | Effect | Explained in |
| --- | --- | --- | --- | --- | --- | --- |
| `browser.url` | `string` | `''` | `env('MEILI_PUBLIC_URL')` | render | Engine address for the browser, scheme included (`https://search.projet.ddev.site`). Without a scheme and a host, the client is not loaded. | [Installation](../installation.md) |
| `browser.key` | `string` | `''` | `env('MEILI_SEARCH_KEY')` | render | Key the browser sends. Readable by every visitor: a search-only key. Empty, the client is not loaded. | [Going to production](../production.md) |
| `apply_mode` | `string` | `submit` | `submit` | render | `immediate`: every checked box searches. `submit`: changes wait for Apply. Any other value falls back to `submit`. Read by the default product listing; a custom `Listing` decides through `applyMode()`. | [How a listing works](../listing/README.md) |
| `url_parameters` | `array<string, string>` | `[]` | `[]` | render | Taxonomy name to URL parameter name. A taxonomy left out travels as `f_<taxonomy>`. | [How a listing works](../listing/README.md) |
| `query_parameters` | `array<string, string>` | `[]` | `[]` | render | Renames a reserved parameter: keys `sort`, `q`, `pg`, `min_price`, `max_price`. | [How a listing works](../listing/README.md) |
| `card.eager` | `int` | `4` | `4` | render | Number of first cards whose image loads eagerly with `fetchpriority="high"`. | [Results](../listing/results-sort-pagination.md) |
| `card.image_size` | `string` | `woocommerce_thumbnail` for products and their variants, `medium` otherwise | commented out | indexing | WordPress image size stored in every card, products included. | [What gets indexed](../indexing/README.md) |
| `engine.reachable_hits` | `int` | `1000` | `1000` | indexing and render | Written as `pagination.maxTotalHits`; caps the last reachable page. | [Index settings](index-settings.md) |
| `engine.max_facet_values` | `int` | `1000` | `1000` | indexing and render | Written as `faceting.maxValuesPerFacet`; a facet returning that many values is reported as truncated. | [Facets](../listing/facets.md) |
| `displayed_attributes` | `list<string>` | `[]` | `[]` | indexing | Document fields the search key may read besides `ID`, `card` and `parent_id`. `*` opens the whole document. | [What gets indexed](../indexing/README.md) |

The module's own `config/config.php` declares one key, `meilifacets.name` (`MeiliFacets`). nwidart merges it over
the project's, so it cannot be overridden; nothing reads it.

### Reserved URL parameters

| Parameter | Carries | Rename with |
| --- | --- | --- |
| `sort` | the sort key | `query_parameters.sort` |
| `q` | the typed search term, 200 characters at most | `query_parameters.q` |
| `pg` | the page number (`/page/N` is honoured too) | `query_parameters.pg` |
| `min_price` | the lower price bound | `query_parameters.min_price` |
| `max_price` | the upper price bound | `query_parameters.max_price` |
| `f_<taxonomy>` | the values of a facet, unless `url_parameters` maps the taxonomy | `url_parameters` |

`php artisan meilifacets:check-parameters` reports the names that collide with WordPress or WooCommerce (see
[commands](commands.md#meilifacetscheck-parameters)).

## Environment variables

The module reads none directly. The published stub bridges two into `browser.*`; MeiliScout reads the others.

| Variable | Read by | Purpose |
| --- | --- | --- |
| `MEILI_PUBLIC_URL` | the stub, as `browser.url` | engine address reached by the visitor's browser |
| `MEILI_SEARCH_KEY` | the stub, as `browser.key`; MeiliScout, for the first render | search-only key |
| `MEILI_HOST` | MeiliScout | engine address reached by PHP: indexing and first render |
| `MEILI_KEY` | MeiliScout | key used to index (write access) |
| `MEILISCOUT_ASYNC_INDEXING` | MeiliScout | defers indexing to WP-Cron when true |

```dotenv
MEILI_HOST=http://meilisearch:7700
MEILI_KEY=<key with write access>
MEILI_SEARCH_KEY=<search-only key>
MEILI_PUBLIC_URL=https://search.projet.ddev.site
```

## MeiliScout settings

Stored by MeiliScout in WordPress options, set from its admin screen.

| Option | Used for |
| --- | --- |
| `meiliscout/indexed_post_types` | The post types indexed. Their taxonomies become facet fields; the public ones not excluded from search become site search types. |
| `meiliscout/indexed_meta_keys` | The meta keys written under `metas`. Empty, MeiliScout writes every meta key. With WooCommerce, the module declares `metas._price` and `metas._stock_status` filterable and searches `metas._sku`: keep them if you set a list. |

## Not configurable

| What | Why it is fixed |
| --- | --- |
| The index name | MeiliScout's post index (`posts`). |
| Document field names (`facets`, `labels`, `card`, `price`…) | The client and the index settings read them. See [index settings](index-settings.md). |
| `faceting.sortFacetValuesBy` | Always `count`, so the most frequent values survive the ceiling. |
| The `48em` breakpoint of the stylesheets | Written in their media queries. |
| The browser client's request timeout | 5 seconds. |
| `meiliscout/post/displayed_attributes` | Overwritten by the module's `displayedAttributes`. |

## Watch out

- `browser.url` without its scheme leaves the client off, without a message.
- Do not run `php artisan module:publish-config MeiliFacets --force`: it overwrites `config/meilifacets.php` with
  the module's `config/config.php`.
- Renaming a URL parameter breaks every link already shared with the old name.

## See also

- [Installation](../installation.md)
- [Going to production](../production.md)
- [Commands](commands.md)
- [Index settings and document fields](index-settings.md)
