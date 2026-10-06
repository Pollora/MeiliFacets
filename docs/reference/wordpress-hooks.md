# WordPress filters and actions

The filter the module exposes, the WordPress and plugin filters it reads, the hooks it attaches to, and the asset
handles it registers.

You want to…

- [change the load priority of the browser client, or say which pages render a listing](#filters-the-module-exposes);
- [know which WordPress or WooCommerce filters change the module's output](#filters-the-module-reads);
- [know which hooks the module attaches to, to spot a conflict](#hooks-the-module-attaches-to);
- [dequeue or inspect a stylesheet or script](#handles).

```php
add_filter('meilifacets/script_fetchpriority', function (string $priority, string $module): string {
    return $module === '@meilifacets/listing' ? 'high' : $priority;
}, 10, 2);
```

## Filters the module exposes

| Filter | Arguments | Default | Returns | Applied |
| --- | --- | --- | --- | --- |
| `meilifacets/script_fetchpriority` | `string $priority`, `string $module` (`@meilifacets/listing` or `@meilifacets/site-search`) | `low` | the `fetchpriority` of the module's `<script type="module">` | once per bundle, when a root first asks for it on the page |
| `meilifacets/is_listing_page` | `bool $isListingPage` | `true` once `<x-meilifacets::listing>` is built on the page | whether the page gets the `noindex` and canonical rules, the removal of `rel="next"` and `rel="prev"`, and the preconnect hint | in `wp_head`, by each hook below marked « listing pages » and by `wpseo_canonical` |

They are the only filters the module applies under its own name. See [PHP extension points](../customising/php.md).

## Filters the module reads

WordPress and WooCommerce filters whose value the module uses. A project can hook them as usual.

| Filter | Read when | Default | Effect on the module | Explained in |
| --- | --- | --- | --- | --- |
| `loop_shop_per_page` | render | WooCommerce's columns × rows | page size of the product listing | [Pagination](../listing/results-sort-pagination.md) |
| `excerpt_length` | indexing | `55` | words in the `summary` of a non-product card | [What gets indexed](../indexing/README.md) |
| `wp_img_tag_add_auto_sizes` | indexing | `true` | prefixes the card's `image_sizes` with `auto, ` | [What gets indexed](../indexing/README.md) |
| `query_vars` | `meilifacets:check-parameters` | WordPress's public query vars | the names a listing parameter must not take | [Commands](commands.md#meilifacetscheck-parameters) |

## Hooks the module attaches to

| Hook | Type | Priority | When | What the module does | Explained in |
| --- | --- | --- | --- | --- | --- |
| `wp_enqueue_scripts` | action | 10 | every page | registers the `meilifacets` and `meilifacets-site-search` styles, when published | [Styles](../customising/styles.md) |
| `wp_head` | action | 2 | listing pages | prints `<link rel="preconnect">` to the engine's origin, when the browser connection is set | [How a listing works](../listing/README.md#what-search-engines-see) |
| `after_setup_theme` | action | 10 | every request | puts `<theme>/resources/views/modules/meilifacets` first in the view lookup | [Overriding views](../customising/views.md) |
| `script_module_data_@meilifacets/listing` | filter | 10 | added when a listing root renders | publishes the browser connection and the description of each listing root | [How a listing works](../listing/README.md) |
| `script_module_data_@meilifacets/site-search` | filter | 10 | added when a search root renders | publishes the browser connection and the description of each search root | [Site search](../search/README.md) |
| `wp_robots` | filter | 10 | listing pages | `noindex, follow` on a filtered, sorted, searched or paginated view | [How a listing works](../listing/README.md) |
| `wpseo_canonical` | filter | 20 | same views | points Yoast's canonical to the bare path, page number kept | [How a listing works](../listing/README.md) |
| `wpseo_next_rel_link` | filter | 10 | listing pages | removes Yoast's `rel="next"` | [How a listing works](../listing/README.md) |
| `wpseo_prev_rel_link` | filter | 10 | listing pages | removes Yoast's `rel="prev"` | [How a listing works](../listing/README.md) |
| `woocommerce_enable_post_clause_filtering` | filter | 10 | every request | returns `false`: WooCommerce no longer narrows a product archive's main query from the URL | [Indexed prices](../indexing/prices.md) |
| `woocommerce_get_tax_location` | filter | 10 | added while a document is indexed, removed right after | taxes at the shop's base address | [Indexed prices](../indexing/prices.md) |
| `rocket_delay_js_exclusions` | filter | 10 | when WP Rocket asks | excludes the published bundles from Delay JS | [Going to production](../production.md) |
| `meiliscout/post/document` | filter | 10 | MeiliScout builds a post document | adds `facets`, `labels`, `excerpt`, `content`, `card`, `price`, `in_stock`, `document_kind` | [What gets indexed](../indexing/README.md) |
| `meiliscout/indexables` | filter | 10 | MeiliScout lists its indexables | replaces its post indexable with the module's, which writes the index settings | [Index settings](index-settings.md) |
| `woocommerce_new_product_variation`, `woocommerce_update_product_variation`, `woocommerce_before_delete_product_variation`, `woocommerce_trash_product_variation` | action | 10 | a variation is saved, deleted or trashed | remembers its product | [What gets indexed](../indexing/README.md#when-to-reindex) |
| `added_post_meta`, `updated_post_meta`, `deleted_post_meta` | action | 10 | a meta is written | remembers the product of a variation whose meta changed | [What gets indexed](../indexing/README.md#every-meta-write-reindexes-the-post) |
| `shutdown` | action | 20 | end of the request | fires `meiliscout/reindex_post` once per remembered product | [What gets indexed](../indexing/README.md#when-to-reindex) |
| `edited_term` | action | 20 | after WooCommerce rewrote a renamed attribute term in SQL | clears the meta cache of the products and variations it rewrote | [What gets indexed](../indexing/README.md#when-to-reindex) |
| `woocommerce_attribute_updated` | action | 10 | an attribute is saved | remembers a renamed attribute | [What gets indexed](../indexing/README.md#when-to-reindex) |
| `shutdown` | action | 10 | end of the request, after an attribute rename | clears the meta cache of its variations, fires `meiliscout/schedule_indexation` | [What gets indexed](../indexing/README.md#when-to-reindex) |

Most of these are declared with `#[Filter]` and `#[Action]` attributes, which Pollora discovers and caches: after an
update of the module, run `php artisan discovery:clear`.

## Handles

| Handle or id | Kind | Published file | Loaded |
| --- | --- | --- | --- |
| `meilifacets` | style | `public/modules/meilifacets/css/meilifacets.css` | on a page that renders `<x-meilifacets::listing>` |
| `meilifacets-site-search` | style | `public/modules/meilifacets/css/site-search.css` | on a page that renders `<x-meilifacets::search>` |
| `@meilifacets/listing` | script module | `public/modules/meilifacets/dist/listing.js` | with a listing root, when the browser connection is set |
| `@meilifacets/site-search` | script module | `public/modules/meilifacets/dist/site-search.js` | with a search root, when the browser connection is set |
| `site-search-client.js` | dynamic import, not a handle | `public/modules/meilifacets/dist/site-search-client.js` | the first time a visitor reaches for the search |
| `wp-script-module-data-@meilifacets/listing` | `<script type="application/json">` id | none | printed by WordPress with the listing bundle |
| `wp-script-module-data-@meilifacets/site-search` | `<script type="application/json">` id | none | printed by WordPress with the search bundle |

A stylesheet asked for after `wp_head` is printed where the root renders instead of in the `<head>`.

```php
add_action('wp_enqueue_scripts', function (): void {
    wp_dequeue_style('meilifacets');
    wp_deregister_style('meilifacets');
}, 20);
```

## Watch out

- `woocommerce_enable_post_clause_filtering` is switched off for every request, not only on pages with a listing.
- Yoast's `rel="next"` and `rel="prev"` are removed on every page that renders a listing, filtered or not.
- The module overrides MeiliScout's `meiliscout/post/displayed_attributes`: its value never reaches the index.
- A dequeued stylesheet leaves the `hidden` rule and the drawer sheet to the theme. See
  [Styles and design tokens](../customising/styles.md).

## See also

- [PHP contracts](contracts.md)
- [PHP extension points](../customising/php.md)
- [Index settings and document fields](index-settings.md)
