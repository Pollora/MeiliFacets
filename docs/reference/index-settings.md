# Index settings and document fields

The Meilisearch settings the module writes on the `posts` index, and the fields it adds to each document.

You want to…

- [know which settings the module writes, and from what](#index-settings);
- [know when they reach the engine](#when-settings-are-written);
- [look up a document field](#document-fields);
- [look up a card field](#card-fields);
- [look up a price or meta field](#price-and-meta-fields).

```php
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\PriceField;

DocumentField::Facets->path('pa_color'); // "facets.pa_color"
PriceField::Min->path();                 // "price.min"
```

## Index settings

Written by the module's post indexable (`Indexing\FacetedPostIndexable`), which MeiliScout uses in place of its
own. Settings are named by `Enums\IndexSetting`, `Enums\FacetingSetting`, `Enums\PaginationSetting` and
`Enums\TypoToleranceSetting`.

| Setting | Value written | Comes from | Explained in |
| --- | --- | --- | --- |
| `filterableAttributes` | MeiliScout's own, plus `facets.<taxonomy>` for every taxonomy of an indexed post type, plus `IndexAttributes::filterable()`: `metas._price`, `metas._stock_status`, `price.min`, `price.max`, `price.onsale` with WooCommerce | MeiliScout, `IndexAttributes` | [What gets indexed](../indexing/README.md) |
| `sortableAttributes` | MeiliScout's own, plus `IndexAttributes::sortable()`: `price.min`, `price.max` with WooCommerce | MeiliScout, `IndexAttributes` | [Sorting](../listing/results-sort-pagination.md) |
| `displayedAttributes` | `ID` and `card`, plus `IndexAttributes::displayed()` and `displayed_attributes`; `["*"]` when either holds `*` | `IndexAttributes`, configuration | [What gets indexed](../indexing/README.md) |
| `faceting.sortFacetValuesBy` | `{"*": "count"}` | fixed | [Facets](../listing/facets.md) |
| `faceting.maxValuesPerFacet` | `engine.max_facet_values` (`1000`) | configuration | [Facets](../listing/facets.md) |
| `pagination.maxTotalHits` | `engine.reachable_hits` (`1000`) | configuration | [Pagination](../listing/results-sort-pagination.md) |
| `searchableAttributes` | `SearchableAttributes::all()`: `post_title`, then `labels.product_brand`, `labels.product_cat`, `metas._sku` with WooCommerce, then `labels.<taxonomy>` for the other indexed taxonomies, then `excerpt`, `content` | `SearchableAttributes` | [Search relevance](../indexing/relevance.md) |
| `typoTolerance.disableOnAttributes` | `IndexAttributes::exactlyMatched()`: `metas._sku` with WooCommerce | `IndexAttributes` | [Search relevance](../indexing/relevance.md) |

MeiliScout's own lists: `filterableAttributes` holds `post_type`, `post_status`, `terms.term_id`, `terms.slug`,
`terms.name`, `terms.taxonomy`, `terms.term_taxonomy_id` and `metas.<key>` for its indexed meta keys;
`sortableAttributes` holds `post_title`, `post_date` and the same `metas.<key>`.

Taxonomies left out of `labels` and of the search order (`ProductTaxonomy::technical()`): `product_visibility`,
`product_type`, `product_shipping_class`, `pos_product_visibility`. They stay filterable under `facets`.

### When settings are written

MeiliScout pushes the settings every time it makes sure the index exists: on every indexing run, and on every
post saved. A changed setting reaches the engine at the next of these. Settings changed by hand on the engine are
overwritten.

## Document fields

Named by `Enums\DocumentField`. “Written by” says who puts the field in the document.

| Field | Written by | Content | Read by |
| --- | --- | --- | --- |
| `ID` | MeiliScout | the post ID | the browser (card identity) |
| `post_type` | MeiliScout | the post type | filters |
| `post_status` | MeiliScout | the status | filters (`publish`) |
| `post_title` | MeiliScout | the title | search |
| `terms` | MeiliScout | the post's terms, each with `term_id`, `name`, `slug`, `taxonomy`, `term_taxonomy_id`, `parent` | the module, to build `facets` and `labels` |
| `metas` | MeiliScout | meta values by key | filters, search (`metas._sku`) |
| `facets` | the module | `facets.<taxonomy>`: the term slugs, ancestors included | facet filters and counts |
| `labels` | the module | `labels.<taxonomy>`: the term names as plain text, ancestors included; technical taxonomies left out | search |
| `excerpt` | the module | the excerpt as plain text | search |
| `content` | the module | the content as plain text | search |
| `card` | the module, through `CardProjector` | the fields a card shows (below) | the browser |
| `price` | the module, WooCommerce products only | `min`, `max`, `onsale` (below) | price filter, sorts |

## Card fields

Named by `Enums\CardField`. The default projectors write them; a `CardProjector` of yours can add others.

| Field | Written by | Content |
| --- | --- | --- |
| `title` | `DefaultCardProjector` | the title as plain text |
| `url` | `DefaultCardProjector` | the permalink |
| `image_url` | `DefaultCardProjector` | the featured image at `card.image_size` |
| `image_srcset` | `DefaultCardProjector` | its `srcset`, when WordPress has one |
| `image_sizes` | `DefaultCardProjector` | its `sizes`, prefixed with `auto, ` while `wp_img_tag_add_auto_sizes` is true |
| `image_alt` | `DefaultCardProjector` | the image's alternative text |
| `image_width` | `DefaultCardProjector` | width in pixels |
| `image_height` | `DefaultCardProjector` | height in pixels |
| `price` | `WooCommerceCardProjector` | the product's price HTML, as WooCommerce formats it |
| `summary` | `SummaryCardProjector`, posts that are not products | the excerpt cut to `excerpt_length` words |
| `id` | not indexed: added to each card from `ID` on the server and in the browser | the post ID |
| `variants` | a `CardProjector` of yours | the ways the product is sold, each a `Listing\CardVariant`: see [Card variants](../customising/card.md#card-variants). A variant's `fields` cannot set `id`, `variants` or `several_variants`: the module drops them. The module writes the list as a JSON list when it indexes the document, even when the projector's array has gaps. Never bound: removed from the cards the module shows — the listing, on the server and in the browser, and the search panel — after picking one when a filter concerns them. A card a project renders itself is not concerned |
| `several_variants` | not indexed: set when several variants match the active filters | `true`, or absent |

A variant is stored as:

| Key | Enum | Content |
| --- | --- | --- |
| `facets` | `VariantField::Facets` | taxonomy to the term slugs the variant carries, as the facets hold them |
| `price` | `VariantField::Price` | its displayed price, a number on the same scale as `price.min` and `price.max` |
| `fields` | `VariantField::Fields` | the card fields shown instead of the product's when it is chosen |

Image fields are absent when the post has no featured image; `summary` is absent when empty.

## Price and meta fields

| Field | Enum | Content | Used by |
| --- | --- | --- | --- |
| `price.min` | `PriceField::Min` | lowest displayed price (a variable or grouped product's cheapest child) | price filter, `price_asc` |
| `price.max` | `PriceField::Max` | highest displayed price | price filter, `price_desc` |
| `price.onsale` | `PriceField::OnSale` | `true` when WooCommerce lists the product as on sale | `on_sale` |
| `metas._price` | `ProductMeta::Price` | WooCommerce's `_price` meta | filterable |
| `metas._stock_status` | `ProductMeta::StockStatus` | WooCommerce's `_stock_status` meta | filterable |
| `metas._sku` | `SearchedMeta::Sku` | WooCommerce's `_sku` meta | search, without typos |

Prices are the ones the shop displays, taxes included or not as the shop is set, at the shop's base address. A
product without a price has no `price` field. See [Indexed prices](../indexing/prices.md).

## Watch out

- A field added to `filterableAttributes` or `sortableAttributes` is usable only once the settings are written.
  Until then the engine refuses the query, and the listing shows its unavailable message.
- `displayedAttributes` decides what the public search key can read. `*` exposes every field of every document.
- `card.variants` travels with every hit. Each variant weighs about what its `fields` hold — a few links, a price and a
  displayed value come to a few hundred bytes — and the search panel receives it twice, in `card` and in its highlighted
  copy `_formatted.card`, before dropping it. The panel cannot leave it out: Meilisearch (checked on 1.53) has no way to
  exclude a sub-field from `attributesToRetrieve` or `attributesToHighlight`, naming `card.title` returns nothing while
  `displayedAttributes` holds `card`, and highlighting a sub-field alone returns no `_formatted`. Keep `fields` to what
  a card shows.
- Reindex after changing taxes, `card.image_size`, `excerpt_length` or a projector: the documents keep what they
  were built with.

## See also

- [What gets indexed](../indexing/README.md)
- [Configuration and environment](configuration.md)
- [PHP contracts](contracts.md)
