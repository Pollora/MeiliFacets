# Going to production

This page covers what to run on every deployment, how to keep the index in step with the site, how to secure the
public search key and the engine, and the failures that give no warning.

You want to…

- [know what to run on every deployment](#deployment-checklist);
- [know when a deployment needs a reindex](#when-a-deployment-needs-a-reindex);
- [make sure cron runs](#cron-must-run);
- [decide on deferred indexing](#deferred-indexing);
- [understand why saving a post can push several documents](#every-meta-write-reindexes);
- [freeze the URL parameter names](#url-parameter-names);
- [choose the engine version](#engine-version);
- [secure the search key and the engine](#security);
- [know what search engines see](#robots).

A deployment script, after the code and `composer install`:

```bash
php artisan module:publish MeiliFacets
php artisan meilifacets:check-assets      # exits 1 when a published copy is missing or stale
php artisan discovery:clear
# clear the page cache and any minified copy of the assets
wp meiliscout index                       # only when the deployment changes what is indexed
```

## Deployment checklist

1. **Publish the assets**: `php artisan module:publish MeiliFacets`. The stylesheets and the client are copied into
   `public/modules/meilifacets`; the module's folder is outside the web root.
2. **Check the publication**: `php artisan meilifacets:check-assets`. It exits with code `1` when a copy is missing
   or older than the module's. Make it fail the deployment, and run it in continuous integration too.
3. **Clear the discovery cache** after an update of the module or a new `Listing` class:
   `php artisan discovery:clear`.
4. **Clear the page cache**, and any minified copy a cache plugin keeps of the stylesheets and scripts.
5. **Reindex** when the deployment changes what is indexed or the index settings; see the next section.
6. **Check the environment** of a new server: `MEILI_HOST`, `MEILI_KEY`, `MEILI_SEARCH_KEY`, `MEILI_PUBLIC_URL`,
   MeiliScout activated and its content selection made (both live in the database).

Steps 1 and 2 matter more than they look. A missing publication is visible: nothing filters, and the elements the
client hides show. A **stale** publication is not: the old stylesheet and the old client run without error against
markup rendered by the new code, and the page is wrong in ways no log reports.

## When a deployment needs a reindex

Saving a post reindexes that post. Anything else that changes what a document holds, or the index settings, needs a
full reindex:

```bash
wp meiliscout index
```

Reindex when the deployment changes:

- the filterable or sortable fields (`IndexAttributes`, including a new facet on a taxonomy or a new sort);
- the searched fields or their order (`SearchableAttributes`);
- `engine.*`, `displayed_attributes` or `card.image_size` in `config/meilifacets.php`;
- the card projector, or anything a card shows;
- the shop's tax settings, address, currency or price format;
- MeiliScout's indexed post types.

The index settings reach the engine only when MeiliScout indexes. Until then, the engine refuses a query on a field
that is not filterable or sortable yet, and **the whole listing is replaced** by « Search is temporarily unavailable.
Please try again in a moment. », served with status `503`. The full table, images included, is in
[What gets indexed](indexing/README.md#when-to-reindex).

## Cron must run

Pollora switches WP-Cron off by default (`DISABLE_WP_CRON`), on every environment, and puts nothing in its place.
Nothing in the module or in MeiliScout warns when cron does not run: the index simply drifts from the site.

| Queue | What stops without it |
| --- | --- |
| WP-Cron | MeiliScout's deferred indexing, and the reindexing started from its admin screen |
| Action Scheduler (WooCommerce) | scheduled sales starting and ending, so the indexed prices and the price filter |

Action Scheduler is run by WP-Cron as well. Without cron, it only starts on an admin request, through a loopback
request to `admin-ajax.php`. Behind HTTP Basic authentication that loopback gets a `401` and the queue stops.

Run WP-Cron from the system cron. With WP-CLI, from the project root:

```bash
*/5 * * * * cd /var/www/project && wp cron event run --due-now > /dev/null 2>&1
```

Calling `wp-cron.php` over HTTP every few minutes works too. A project can also switch WP-Cron back on through
Pollora's `config/wordpress.php` (`'constants' => ['disable_wp_cron' => false]`), at the cost of running it inside
visitors' requests.

To find out whether cron runs, list the next due events. If the oldest date is several days old, nothing runs:

```bash
wp cron event list --fields=hook,next_run_gmt --format=csv | sort -t, -k2 | head -5
```

Run the queues by hand on an environment without cron:

```bash
wp cron event run --due-now
wp action-scheduler run
```

## Deferred indexing

By default, MeiliScout pushes a document to Meilisearch inside the request that saves the post. Its deferred mode
queues the write and pushes the queue later, from WP-Cron. Switch it on with an environment variable:

```dotenv
MEILISCOUT_ASYNC_INDEXING=true
```

The variable wins over MeiliScout's `meiliscout/meiliscout_async_indexing` option. Do not define it as a PHP constant.

| | Immediate (default) | Deferred |
| --- | --- | --- |
| Saving a post | waits for the engine | does not call the engine |
| Several writes to one post | one push per write | one queue entry |
| Index freshness | immediate | up to five minutes later (MeiliScout's `meiliscout/async_indexing_delay` filter, in seconds) |
| Without cron | index up to date | **index never updated** |

The last row is why deferred indexing is only switched on once cron is proven to run.

## Every meta write reindexes

MeiliScout reindexes a post on every meta write, whatever the meta key. WooCommerce writes `_regular_price` then
`_price` when a price changes, so the product is pushed twice. A plugin that saves metas over AJAX, such as a bulk
editor, pushes one document per field it saves.

Two counter-measures, in this order:

1. **Deferred indexing**, above: writes are queued and pushed once.
2. **MeiliScout's `meiliscout/skip_indexing` filter**, to switch indexing off in one identified context, then
   reindex once at the end:

   ```php
   add_filter('meiliscout/skip_indexing', fn (bool $skip): bool => $skip || defined('ACME_IMPORT_RUNNING'));
   ```

Write the filter for a case you have observed, never as a precaution: a skipped write leaves a stale document, and
nothing says so. Details in [What gets indexed](indexing/README.md#every-meta-write-reindexes-the-post).

## URL parameter names

Filtered URLs are shared, bookmarked and sometimes indexed by search engines before the `noindex` is read. **Choose
the parameter names before going live**: renaming one later breaks every link already out there.

```php
// config/meilifacets.php
'url_parameters' => [
    'product_cat' => 'category',
    'product_brand' => 'brand',
],
```

Then check them against the names WordPress, WooCommerce and common caching proxies already use:

```bash
php artisan meilifacets:check-parameters
```

The command exits with code `1` on a blocking collision. Run it again after adding a taxonomy, a product attribute
or a plugin. The parameters are described in [How a listing works](listing/README.md#the-url-is-the-state).

## Engine version

The module is developed and tested against a recent Meilisearch (1.5x). It has not been validated on 1.10.x or
older, and the exact minimum version has not been measured. Run production on the same version as development, and
re-check the listing and the search after upgrading the engine.

## Security

### The search key

The browser queries Meilisearch directly, so `browser.key` is printed in every page that holds a listing or a search.
It must be a **search-only key**, restricted to the `search` action on the `posts` index. **Never the master key**,
and never `MEILI_KEY`, which can write to the index.

Create it once with the master key:

```bash
curl -X POST 'http://meilisearch:7700/keys' \
  -H 'Authorization: Bearer <master key>' \
  -H 'Content-Type: application/json' \
  --data '{
    "name": "Browser search",
    "description": "Search-only key for MeiliFacets",
    "actions": ["search"],
    "indexes": ["posts"],
    "expiresAt": null,
    "uid": "<a fixed UUID>"
  }'
```

The `key` field of the response goes into `MEILI_SEARCH_KEY`. A key's value is derived from its `uid` and the master
key: giving a fixed `uid` lets you recreate the same key after the engine's data is lost, without changing the
environment.

The key only reads what the index lets it read: the module restricts what a response returns to `ID` and `card`
(Meilisearch's `displayedAttributes`). A field added through `displayed_attributes` becomes readable by every visitor.
Filtering and counting are not restricted by that setting.

### Rate limiting

Meilisearch has no rate limiting of its own, and the public key lets anyone send searches. Put a rate limit on the
engine's public URL, at the reverse proxy in front of it. With nginx, for example:

```nginx
limit_req_zone $binary_remote_addr zone=meilisearch:10m rate=10r/s;

server {
    server_name search.projet.ddev.site;

    location / {
        limit_req zone=meilisearch burst=30 nodelay;
        proxy_pass http://meilisearch:7700;
    }
}
```

Leave room for real use: a visitor typing in the search sends one request per pause, and each gesture on a listing
sends one.

Filtered and searched URLs are usually not served from a page cache, since each one carries its own query string.
Every first render of such a URL reaches PHP and the engine.

### What the index may hold

Only published content. The search key can search the whole `posts` index, so anything indexed can be found by
anyone holding the key.

- MeiliScout indexes only public statuses (`publish` by default, through its `meiliscout/indexable_post_statuses`
  filter) and never sends a post's password or the text of a password-protected post. This is the behaviour of the
  current `feat/meilifacets` branch.
- Do not widen `meiliscout/indexable_post_statuses` to drafts, private or pending posts.
- A projector adding fields to the card makes them public: see [What gets indexed](indexing/README.md#the-card).
- The client writes the card's price as HTML. Whoever can write to the index can therefore put markup in front of
  visitors: keep `MEILI_KEY` and the master key on the server.

### A known structural limit

The base filter of a listing (post type, published status, catalogue visibility of a product) is sent by the
browser along with the visitor's filters. Anyone can rewrite it and query the whole index with the public key. The
listing's filter is a convenience, not an access control.

The index must therefore **never hold content that must stay private**: private products, members-only posts,
hidden catalogue entries whose existence is confidential. A tenant token would move the base filter into the engine,
signed by the server; it is not implemented yet.

## Robots

On an archive or a search page, every view that is not the bare path is served `noindex, follow`: a facet value, a
sort, a search term, a price bound or a page number, in a parameter or as `/page/N`.

- These views carry **no canonical**: a `noindex` and a canonical pointing elsewhere are contradictory signals. The
  bare path keeps its canonical, and `rel="next"` / `rel="prev"` are removed from listing pages.
- Facets, sorting and pagination are **buttons, not links**. Crawlers do not follow them, so they do not discover the
  combinations of filters.
- Search terms read from the URL are escaped wherever they are printed.
- A listing placed on an ordinary page, not an archive or search page, is not covered by these rules.

Details in [How a listing works](listing/README.md#what-search-engines-see).

## Watch out

- **A stale publication is silent.** Make `meilifacets:check-assets` fail the deployment.
- **Changing a filterable or sortable field without reindexing removes the listing** from every page, with a `503`.
- **Without cron, the index drifts** and scheduled sales never reach the prices. Nothing reports it.
- **Deferred indexing without cron never updates the index.**
- **MeiliScout's settings live in the database.** A new environment starts with nothing indexed.
- **The search key is public, and so is the base filter.** Keep private content out of the index.

## See also

- [Installation](installation.md)
- [What gets indexed](indexing/README.md)
- [Indexed prices (WooCommerce)](indexing/prices.md)
- [Commands](reference/commands.md)
- [Configuration and environment](reference/configuration.md)
- [Troubleshooting and FAQ](troubleshooting.md)
