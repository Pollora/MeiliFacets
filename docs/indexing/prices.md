# Indexed prices (WooCommerce)

This page covers the price the module indexes for each product: which one, how taxes and scheduled sales affect it,
and why WooCommerce's own price filtering is switched off on the module's pages.

You want to…

- [know which price the filter compares against](#the-displayed-price-not-the-entered-one);
- [understand variable and grouped products](#the-displayed-price-not-the-entered-one);
- [know what happens with taxes](#taxes);
- [explain why a product at 40,83 € is excluded below 40,83](#no-rounding);
- [know when a scheduled sale reaches the listing](#scheduled-sales);
- [keep WooCommerce's native filtering on one archive](#woocommerces-own-price-filtering-is-switched-off).

## The displayed price, not the entered one

Each product with a price carries a `price` field:

```json
{
    "price": { "min": 28, "max": 62, "onsale": false }
}
```

`min` and `max` are **the price the shop displays on the product's card**, not the price entered in the admin. The
module asks WooCommerce for it when the product is indexed, and never recomputes it:

| Product | Price indexed |
| --- | --- |
| simple, external | the displayed price (`wc_get_price_to_display()`), as both `min` and `max` |
| variable | the lowest and highest prices of its variations (`get_variation_prices(true)`): the range its card shows |
| grouped | the lowest and highest displayed prices of its visible children; a child with no price is skipped |

The price filter and the price sorts read these two fields. A product sold from 28 to 62 answers a filter from 40 to
70: the filter keeps every product whose range overlaps the one the visitor asked for, as WooCommerce does. See
[Price filter](../listing/price.md).

`onsale` is true when the price actually charged is the sale price: for a variable product, when one of its visible
variations is; a grouped product is never on sale. It feeds the « On sale » option of the sort menu, which shows only
the products on sale.

**A product with no price** (the price field emptied in the admin) gets no `price` field. It matches no price range,
does not pull the slider's lower bound to 0, and comes last in both price sorts. A price of `0` entered on purpose is
kept: a free product is a real product.

**A grouped product** counts only its published children. The product is indexed as an anonymous visitor, so a
draft child never lends its price, whoever triggered the indexing. A grouped product with no child that has a price
gets no `price` field, whatever label the theme shows in its place.

The card's price, `card.price`, is WooCommerce's price HTML (`get_price_html()`), computed under the same conditions.
The two always agree.

## Taxes

The price is indexed as WooCommerce displays it in the shop, under its tax settings:

| Prices entered | « Display prices in the shop » | Price indexed |
| --- | --- | --- |
| excluding tax | including tax | the entered price plus tax |
| including tax | excluding tax | the entered price minus tax |
| either | same as entered | the entered price |

Each product is converted with its own tax class. Without taxes enabled, a simple product keeps its entered price.

The tax is always computed **at the shop's address** (WooCommerce > Settings > General), whoever triggers the
indexing. Without that, a quick edit by an administrator located in another country would index that country's tax.

When the visitor filters, nothing is converted: the bound typed in the field, the slider, the card and the document
all speak the displayed price.

**A tax change does not reindex anything.** Changing a rate, enabling taxes, or changing « Prices entered with tax »,
« Display prices in the shop » or the shop's address leaves the index, and the cards, at their old values until you
reindex:

```bash
wp meiliscout index
```

## No rounding

The module keeps the value WooCommerce returns, without rounding it. A product entered at 49 € including tax, in a
shop that displays prices excluding a 20 % tax, is indexed at `40.833333`. Its card displays 40,83 €, and a filter
`?max_price=40.83` excludes it, because 40.833333 is above 40.83.

Variable products are not affected: WooCommerce already rounds their prices to the shop's number of decimals.

## Scheduled sales

A sale scheduled in the admin starts and ends through WooCommerce's Action Scheduler. When the sale switches,
WooCommerce writes the product's `_price` meta, and that write reindexes the product: the listing follows without
anything to do.

This only works if Action Scheduler runs. Without a system cron, it runs only on administration requests: a visit to
the front end does not move the queue, and the sale waits for the next administrator to open the back office. Pollora
switches WP-Cron off by default, so set up a cron: see [Going to production](../production.md).

While the switch is late, the product page may already show the sale badge (computed on the fly from the dates),
while the cart, the price sorts and the price filter keep the regular price. The listing agrees with the cart: it
shows what will be charged.

The module indexes whether a product is on sale, not the sale's dates or its discount: there is no sort by discount and
no « sales starting soon » filter.

## WooCommerce's own price filtering is switched off

WooCommerce reads `min_price`, `max_price` and `filter_*` straight from the URL and filters the main query of a
product archive with them. On a page the module renders, that work is lost, and it shrinks `found_posts` with a filter
the page does not show.

The module answers `false` to WooCommerce's `woocommerce_enable_post_clause_filtering` filter (WooCommerce 9.9 and
later). WooCommerce asks it only on product archives: the shop, a product search, the archive of any product taxonomy.

| Switched off | Still active |
| --- | --- |
| the native price filter (`min_price`, `max_price`) | the rating filter (`rating_filter`) |
| attribute filtering (`filter_*`), when the product attributes lookup table is enabled | attribute filtering without the lookup table |
| | sorting, visibility, stock, the archive's own term, the search |
| | the main query itself, which still runs |

If one archive keeps WooCommerce's native loop and filters instead of a MeiliFacets listing, switch the filtering back
on for that archive only, after the module:

```php
add_filter(
    'woocommerce_enable_post_clause_filtering',
    fn (bool $enabled, WP_Query $query): bool => $enabled
        || ($query->is_main_query() && $query->is_tax('product_cat', 'accessories')),
    20,
    2,
);
```

Do not use `__return_true`. It would switch filtering back on for every product archive, and for every later
`WP_Query` of the page whenever the URL carries `min_price`, `max_price` or `filter_*`, products or not.

## Watch out

- **WooCommerce still sees the parameter names.** `is_filtered()`, WooCommerce's « Filter products by price » and
  « Active filters » widgets, and the links widgets copy all read `min_price` and `max_price` from the URL. A widget
  shown next to a MeiliFacets listing reacts to them. `php artisan meilifacets:check-parameters` reports the overlap;
  the only way to remove it entirely is to rename the listing's price parameters (see
  [Configuration and environment](../reference/configuration.md)).
- **The price is the shop's, not the visitor's.** A price that varies by role, by customer or by location cannot be
  indexed: the module indexes one price per product, for an anonymous visitor at the shop's address. A tax-exempt
  customer filters and sorts on the same prices as everyone else.
- **The card is frozen HTML.** A change that alters prices without saving the product — a currency change, a role
  discount, a write straight to the database — leaves the card and the index stale until you reindex.
- WooCommerce 9.8 is the minimum for grouped products, and 9.9 for switching off the native filtering.

## See also

- [Price filter](../listing/price.md)
- [What gets indexed](README.md)
- [Going to production](../production.md)
- [Index settings and document fields](../reference/index-settings.md)
