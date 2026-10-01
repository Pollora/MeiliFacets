# What gets indexed

This page covers what the module adds to each document MeiliScout indexes, how to put more data on a card, what the
browser is allowed to read, and when a change needs a reindex.

You want to…

- [know who builds the document](#who-does-what);
- [know what the module adds to it](#fields-the-module-adds);
- [put more data on a card](#adding-fields-to-the-card);
- [let the browser read an extra field](#what-the-browser-may-read);
- [know which index settings the module writes](#settings-the-module-writes);
- [know when a change needs a reindex](#when-to-reindex);
- [understand why saving a post can be slow](#every-meta-write-reindexes-the-post).

## Who does what

**MeiliScout** builds a document for each post of the post types it indexes, and pushes it to an index called
`posts`. The document carries the post's own fields (`ID`, `post_title`, `post_type`, `post_status`…), its `terms` and
its `metas`. Which post types are indexed is a MeiliScout setting, stored in the database and set from its
administration screen: set it on every environment.

**The module** hooks into MeiliScout at two points:

| MeiliScout filter | What the module does |
| --- | --- |
| `meiliscout/post/document` | adds its fields to every document, on every indexing path: full index, single save, async queue |
| `meiliscout/indexables` | replaces MeiliScout's post indexable with its own, which writes the module's index settings |

The module never pushes documents itself, and has no indexing command of its own. To reindex, use MeiliScout's:

```bash
wp meiliscout index
```

## Fields the module adds

| Field | Content |
| --- | --- |
| `facets.<taxonomy>` | the slugs of the post's terms, one field per taxonomy, **ancestors included**: a product filed under « Face › Creams » carries `face` and `creams`. Every taxonomy is projected, WooCommerce's technical ones included |
| `labels.<taxonomy>` | the names of the same terms, as plain text, ancestors included. WooCommerce's technical taxonomies (`product_visibility`, `product_type`, `product_shipping_class`, `pos_product_visibility`) are left out: their terms are flags, not words |
| `excerpt` | the post's own excerpt, as plain text |
| `content` | the post's content, as plain text: block delimiters, tags, shortcodes and entities removed |
| `card` | everything a result card shows: see [The card](#the-card) |
| `price` | `min`, `max` and `onsale`, for a product that has a price: see [Indexed prices](prices.md) |

A password-protected post gets an empty `excerpt`, an empty `content` and no summary on its card.

`facets.<taxonomy>` is what the filters filter on: the module declares it filterable for every taxonomy of every
indexed post type. `labels.<taxonomy>`, `excerpt` and `content` are what the search searches: see
[Search relevance](relevance.md).

The full list of fields and settings is in [Index settings and document fields](../reference/index-settings.md).

## The card

`card` holds what the browser needs to paint a result, already formatted, so that it never calls WordPress back.

| Field | Content | For |
| --- | --- | --- |
| `title` | the post title, as plain text | every post |
| `url` | the permalink | every post |
| `image_url`, `image_width`, `image_height` | the featured image at the size `card.image_size` (default `medium`) | posts with a featured image |
| `image_srcset`, `image_sizes` | the image's candidates, when WordPress has any | posts with a featured image |
| `image_alt` | the image's alternative text, as plain text | posts with a featured image |
| `price` | WooCommerce's price HTML (`get_price_html()`) | products |
| `summary` | the author's excerpt, whole; otherwise the opening of the content, cut at `excerpt_length` words (WordPress filter, 55 by default) | everything but products |

A field with nothing to show is absent, not empty. The exception is a product with no price, whose `card.price` is
WooCommerce's (empty) price HTML.

`summary` is stored decoded: `&` is `&`, not `&amp;`. Render it as text (`{{ }}` in Blade, `textContent` in
JavaScript), never as HTML.

The card is computed when the post is indexed, never when it is displayed. Everything in it is frozen until the next
indexing of that post: see [When to reindex](#when-to-reindex).

### Adding fields to the card

The card is built by a `CardProjector`. The module's default chains several projectors (the base card, the summary,
the WooCommerce price). **Decorate it with `extend()`**: your projector receives the card the module built and adds
to it.

Name your fields with an enum of your own rather than string literals, so that a field name stays a checked
contract:

```php
<?php

declare(strict_types=1);

namespace App\Search;

enum ShopCardField: string
{
    case Brand = 'brand';
}
```

```php
<?php

declare(strict_types=1);

namespace App\Search;

use Modules\MeiliFacets\Contracts\CardProjector;
use WP_Post;
use WP_Term;

final readonly class BrandCardProjector implements CardProjector
{
    private const string BRAND_TAXONOMY = 'product_brand';

    public function __construct(private CardProjector $card) {}

    public function project(WP_Post $post): array
    {
        return [
            ...$this->card->project($post),
            ...$this->brand($post),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function brand(WP_Post $post): array
    {
        $brands = get_the_terms($post, self::BRAND_TAXONOMY);

        if (! is_array($brands) || ! $brands[0] instanceof WP_Term) {
            return [];
        }

        return [ShopCardField::Brand->value => html_entity_decode($brands[0]->name, ENT_QUOTES | ENT_HTML5)];
    }
}
```

```php
// In a service provider of your project
use App\Search\BrandCardProjector;
use Modules\MeiliFacets\Contracts\CardProjector;

public function register(): void
{
    $this->app->extend(
        CardProjector::class,
        fn (CardProjector $card): CardProjector => new BrandCardProjector($card),
    );
}
```

Never `bind()` a projector of your own in place of the default: it would replace the whole chain, WooCommerce price
and summary included.

Rules for a projected field:

- store plain text, never HTML: the client writes card fields as text. The price is the only HTML field;
- leave a field out when there is nothing to show, rather than storing an empty string;
- do not name a field `id`: the module writes the document's `ID` there when it binds a card;
- **anything on the card is public**. Every visitor can read it with the search key: never project private data.

Once the field is indexed, bind it in your card's view: see [Overriding views](../customising/views.md) for the
listing card, and [A card per type](../search/types.md#a-card-per-type) for the search card. Then reindex.

## What the browser may read

The search key travels to every visitor's browser. Anyone can use it to query the index directly, so the module limits
what a response may return through Meilisearch's `displayedAttributes`: **`ID` and `card` only**. Without that limit,
any visitor could read every post's content and every meta, private ones included.

`displayedAttributes` decides what a response returns, not what can be filtered or searched: a filter or a facet count
keeps working on a field the key cannot read.

If your own front-end code needs another field in the hits, declare it in the project's `config/meilifacets.php`:

```php
'displayed_attributes' => ['post_excerpt', 'metas._sku'],
```

A plugin or a package contributes its fields through `IndexAttributes::displayed()` instead: see
[PHP extension points](../customising/php.md).

**`'*'` in this list opens the whole document** to every visitor, every meta included. A project that relies on
MeiliScout's own `WP_Query` integration needs it, because that integration rebuilds a `WP_Post` from the full hit.
Weigh that before adding it.

## Settings the module writes

Each time MeiliScout indexes, the module writes these settings on the index:

| Setting | What the module puts in it |
| --- | --- |
| `filterableAttributes` | MeiliScout's, plus `facets.<taxonomy>` for every indexed taxonomy, plus the WooCommerce price and stock fields when WooCommerce is active |
| `sortableAttributes` | MeiliScout's, plus `price.min` and `price.max` when WooCommerce is active |
| `displayedAttributes` | `ID`, `card`, and what is declared as above |
| `searchableAttributes` | the search order: see [Search relevance](relevance.md) |
| `typoTolerance.disableOnAttributes` | the fields matched exactly: the SKU when WooCommerce is active |
| `faceting` | facet values sorted by count; at most `engine.max_facet_values` values per facet |
| `pagination.maxTotalHits` | `engine.reachable_hits` |

**A setting changed by hand on the index is overwritten** at the next indexing, including the next save of a single
post. Change it through the module's configuration or extension points instead. Every value is detailed in
[Index settings and document fields](../reference/index-settings.md).

## When to reindex

Saving a post reindexes that post. Anything else that changes what a document holds needs a full reindex, because
the document keeps what was computed when it was last indexed.

```bash
wp meiliscout index
```

| Change | Reindex needed? |
| --- | --- |
| a post, a product or its terms saved in the admin, a quick edit, an import through WordPress | no: the post is reindexed |
| a term renamed or moved | no: MeiliScout reindexes the posts filed under it |
| a scheduled sale starting or ending | no, as long as cron runs: see [Indexed prices](prices.md#scheduled-sales) |
| **an image edited**: alternative text changed in the media library, thumbnails regenerated, an image size redeclared | **yes** |
| `card.image_size`, a `CardProjector`, the `excerpt_length` filter | **yes** |
| the permalink structure, the shop's currency or price format | **yes**: `card.url` and `card.price` are frozen |
| tax rates, « Prices entered with tax », « Display prices in the shop », the shop address | **yes**: see [Indexed prices](prices.md#taxes) |
| `SearchableAttributes`, `IndexAttributes`, `displayed_attributes`, `engine.*` | **yes**: see [Search relevance](relevance.md#applying-a-change) |
| MeiliScout's indexed post types | **yes** |
| a write straight to the database, outside WordPress's functions | **yes** |

**A card's image fields are frozen at indexing time.** Its URL, `srcset`, `sizes`, alternative text and dimensions
are read from the image when the post is indexed. Changing a product's featured image reindexes the product, because
the featured image is a meta of the product. Editing the image itself does not reindex the products that show it:
the listing and the search keep the old values until the next indexing. Reindex after `wp media regenerate`:

```bash
wp media regenerate --yes
wp meiliscout index
```

A card that a page renders itself, outside the listing, reads the image on every display and is not affected.

## Every meta write reindexes the post

MeiliScout listens to `added_post_meta`, `updated_post_meta` and `deleted_post_meta` **without filtering on the meta
key**. Any meta written on an indexed post reindexes the whole document: one request to Meilisearch per write.

Most of the time this is what keeps the index right. It becomes visible when a plugin writes metas often:

- WooCommerce writes `_regular_price`, then `_price`, when a price changes: the product is pushed twice;
- a plugin that saves metas over AJAX, a bulk editor for example, reindexes one document per field it saves;
- a script that writes many metas in a loop pushes as many documents.

Two counter-measures, in this order:

1. **MeiliScout's async indexing.** Writes are queued and pushed once, a few minutes later. It needs a running cron:
   without one, the queue is never processed and the index stops following the site. See
   [Going to production](../production.md).
2. **MeiliScout's `meiliscout/skip_indexing` filter**, to switch indexing off in one precise, identified context,
   then reindex once at the end:

   ```php
   add_filter('meiliscout/skip_indexing', fn (bool $skip): bool => $skip || defined('ACME_IMPORT_RUNNING'));
   ```

   Write it for a case you have observed, never as a precaution: a skipped write leaves a stale document, and
   nothing says so.

## Watch out

- **Settings reach the engine only when MeiliScout indexes.** After deploying a new version of the module or changing
  its configuration, the index keeps its old settings until the next indexing.
- **The card is public.** Anything a projector adds can be read by every visitor.
- **A field added to the card is absent from documents indexed before it.** The card degrades (the element is
  removed) but does not break, until you reindex.

## See also

- [Search relevance](relevance.md)
- [Indexed prices (WooCommerce)](prices.md)
- [Going to production](../production.md)
- [Index settings and document fields](../reference/index-settings.md)
- [PHP contracts](../reference/contracts.md)
