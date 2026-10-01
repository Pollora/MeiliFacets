# Search relevance

This page covers which fields the index searches, in which order they rank, and which of them must match exactly. It
applies to the site search and to the listing's search field alike.

You want to…

- [understand what the search order means](#the-search-order);
- [know the default order](#the-default-order);
- [make a field rank higher, or stop a field from being searched](#changing-the-order);
- [switch off typo tolerance on identifiers](#exact-matches);
- [know what the order changes for the site search](#consequences-for-the-site-search);
- [apply a change](#applying-a-change).

## The search order

The module writes Meilisearch's `searchableAttributes` from one list, the search order. It decides two things at once:

- **what is searchable.** A field missing from the list cannot be searched at all, even by a query someone writes
  by hand with the public search key;
- **how a match ranks.** The engine ranks a match by the first field of the list where it is found: a word found in
  the title ranks above the same word found in the content.

## The default order

1. `post_title`;
2. with WooCommerce active: `labels.product_brand`, `labels.product_cat`, `metas._sku`;
3. `labels.<taxonomy>` for every other taxonomy of the indexed post types, in the order WordPress registered them —
   product attributes (`pa_*`) included, WooCommerce's technical taxonomies (`product_visibility`, `product_type`,
   `product_shipping_class`, `pos_product_visibility`) left out;
4. `excerpt`, then `content`.

`labels.*`, `excerpt` and `content` are plain-text fields the module adds to each document: see
[What gets indexed](README.md).

## Changing the order

The order comes from the `SearchableAttributes` contract, whose `all()` method returns the **complete** list, most
important first. Decorate the module's default rather than writing the list out: you receive the default, move or
remove a field, and taxonomies added later still follow.

To rank the excerpt right after the title:

```php
<?php

declare(strict_types=1);

namespace App\Search;

use Modules\MeiliFacets\Contracts\SearchableAttributes;
use Modules\MeiliFacets\Indexing\DefaultSearchableAttributes;

final readonly class ExcerptFirstSearchableAttributes implements SearchableAttributes
{
    private const string EXCERPT = 'excerpt';

    public function __construct(private DefaultSearchableAttributes $default) {}

    public function all(): array
    {
        $fields = array_values(array_diff($this->default->all(), [self::EXCERPT]));
        array_splice($fields, 1, 0, [self::EXCERPT]);

        return $fields;
    }
}
```

```php
// In a service provider of your project
use App\Search\ExcerptFirstSearchableAttributes;
use Modules\MeiliFacets\Contracts\SearchableAttributes;

public function register(): void
{
    $this->app->scoped(SearchableAttributes::class, ExcerptFirstSearchableAttributes::class);
}
```

To stop a field from being searched, remove it with `array_diff()` and do not put it back. To search a meta,
insert its path (`metas._ean`, for example): MeiliScout must index that meta for the field to hold anything.

Bind with `scoped` or `bind`, never `scopedIf`: the module binds its default with `scopedIf`, so a `scopedIf` of yours
is ignored when the module registered first.

## Exact matches

Meilisearch tolerates typos: `hydratnte` finds « Crème hydratante », and `REF20431` also finds `REF20481`. On a
word, that is what the visitor wants; on an identifier, the second match is wrong. The fields matched exactly are
written to `typoTolerance.disableOnAttributes`, from `IndexAttributes::exactlyMatched()`. With WooCommerce active,
the default is the SKU, `metas._sku`.

`IndexAttributes` also declares the filterable, sortable and readable fields. **Decorate it with `extend()`**. The
module binds it with a plain `bind()`: a `bind()` of yours is either overwritten by the module's, or replaces it and
drops the WooCommerce price fields and `displayed_attributes` with it.

```php
<?php

declare(strict_types=1);

namespace App\Search;

use Modules\MeiliFacets\Contracts\IndexAttributes;

final readonly class ExactEanIndexAttributes implements IndexAttributes
{
    private const string EAN = 'metas._ean';

    public function __construct(private IndexAttributes $attributes) {}

    public function exactlyMatched(): array
    {
        return [...$this->attributes->exactlyMatched(), self::EAN];
    }

    public function filterable(): array
    {
        return $this->attributes->filterable();
    }

    public function sortable(): array
    {
        return $this->attributes->sortable();
    }

    public function displayed(): array
    {
        return $this->attributes->displayed();
    }
}
```

```php
// In a service provider of your project
use App\Search\ExactEanIndexAttributes;
use Modules\MeiliFacets\Contracts\IndexAttributes;

public function register(): void
{
    $this->app->extend(
        IndexAttributes::class,
        fn (IndexAttributes $attributes): IndexAttributes => new ExactEanIndexAttributes($attributes),
    );
}
```

An exactly matched field is only searched if it is also in the search order. Changing the order does not change
which fields match exactly, and the other way round.

## Consequences for the site search

Each section of the site search searches a subset of the order (its `searchOn`), and the engine refuses a whole query
that names a field outside the order.

- The default types follow the order on their own: remove `excerpt` from the order, and no section searches it any
  more.
- A type whose fields you set with `withSearchOn()` must stay inside the order, or the search throws when it renders.
- A type left with no field throws too.

The messages and the fix are in [Searchable content types](../search/types.md#fields-searched).

The order also ranks the results of the shop listing's search, which searches the `product` type's fields.

## Applying a change

The settings reach Meilisearch only when MeiliScout indexes. After changing the order or the exact matches:

```bash
php artisan discovery:clear
wp meiliscout index
```

Until then, the index searches with its previous settings. A site search section whose `searchOn` names a field the
index does not search yet is refused by the engine, and the panel shows « Search unavailable ».

## Watch out

- **A setting changed by hand on the index is overwritten** at the next indexing, including the next save of a single
  post. Change the order through `SearchableAttributes`, never in Meilisearch's dashboard.
- **Adding a technical taxonomy to the order gives an empty field.** Documents carry no `labels` for
  `product_visibility`, `product_type`, `product_shipping_class` or `pos_product_visibility`, and that list is fixed in
  the module.
- **Ranking is by field, then by Meilisearch's ranking rules.** The module changes neither the ranking rules nor the
  matching strategy.

## See also

- [What gets indexed](README.md)
- [Searchable content types](../search/types.md)
- [PHP contracts](../reference/contracts.md)
- [Index settings and document fields](../reference/index-settings.md)
