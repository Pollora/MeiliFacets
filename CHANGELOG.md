# Changelog

All notable changes to MeiliFacets are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

What to change in a project when moving from one version to the next is in
[docs/upgrading.md](docs/upgrading.md).

## [Unreleased]

## [0.3.0](https://github.com/Pollora/MeiliFacets/releases/tag/0.3.0) - 2026-10-19

Seventh beta, on MeiliScout 2.5.1, the first tagged release of MeiliScout the module can require: a project no longer
requires a MeiliScout branch itself. MeiliScout 2 names its indexes with a prefix, so every environment needs a new
search key on the prefixed indexes and a full indexation, `wp meiliscout index --clear`: see
[docs/upgrading.md](docs/upgrading.md). Listings render and rank as before; browser tests with Playwright come with the
module.

### Changed

- Requires MeiliScout `^2.5.1` instead of its `feat/meilifacets` branch: 2.5.0 loses the reindex of a product whose
  variation changed (AmphiBee/MeiliScout#75). MeiliScout 2.5.1 brings `symfony/http-client` and `nyholm/psr7`. Since
  MeiliScout 2.0, index names start with a prefix (`MEILI_INDEX_PREFIX`, or the site's domain). The project no longer
  requires MeiliScout itself.
- The posts index ranks by relevance before the chosen sort (`rankingRules`), as it did under MeiliScout 1:
  since 2.0, MeiliScout ranks by the sort first.

### Fixed

- Listings search the index MeiliScout searches, its active one, and not the one it writes to: the two differ while a
  migration is pending, as on a site updated from MeiliScout 1 until its first full indexation, and listings answered
  `503` meanwhile. The browser is handed the same index.
- A console command run without WordPress, such as `composer install`, no longer logs an error per hooked class of the
  module: the browser connection reads the searched index when it is first used.

### Added

- Browser tests with Playwright, `npm run test:playwright`: facets, active values, price range, variant facet, sorts,
  listing search, pagination, site search, on a desktop and on a phone. They run against the site in
  `MEILIFACETS_PLAYWRIGHT_URL`, on the listing at `MEILIFACETS_PLAYWRIGHT_LISTING` and the category page at
  `MEILIFACETS_PLAYWRIGHT_CATEGORY`, searching `MEILIFACETS_PLAYWRIGHT_TERM` and filtering on the variant taxonomy
  `MEILIFACETS_PLAYWRIGHT_VARIANT_TAXONOMY`.

### Known issues

- A facet declared with `SelectionMode::Single` hides its other values once one is picked
  ([#4](https://github.com/Pollora/MeiliFacets/issues/4)).
- A failed search in the browser shows no message, and the URL and controls keep the filter that was not applied
  ([#5](https://github.com/Pollora/MeiliFacets/issues/5)).
- Saving a product used to re-index it once per meta WooCommerce writes, each pass resending the index settings.
  MeiliScout 2.5 sends the writes of a request once, at its end, and resends the settings only when they change; this
  has not been measured with the module yet.
- MeiliScout's *Relevance* screen has no effect: the module writes its own searched fields at every indexation.
- MeiliScout's suggestion field is not supported alongside the module: it reads fields the module does not display.
- On a WooCommerce attribute archive (`/pa_volume/400ml/`), the card does not show the variant of the term browsed.
  Attribute archives are outside the module's scope: leave « Enable archives? » unchecked.
- A product whose variants share a term counts once per variant under that term.
- Past the row limit on a wide screen, the side sheet needs the browser client: when it does not start, the
  « Filters » or « Show more » button opens nothing.
- A theme that moves the drawer's `media` past `48em` also redeclares the side sheet block.
- « Popularity » and « Average rating » cannot be the listing's default order yet.

## [0.2.4](https://github.com/Pollora/MeiliFacets/releases/tag/0.2.4) - 2026-10-19

Sixth beta, on accessibility. The drawer's title and the site search sections no longer add headings to the page; the
active filters and the listing search field are named by visually hidden text; every list styled without bullets
keeps its list role. A page no longer fails on Yoast's empty canonical. Run `php artisan module:publish MeiliFacets`:
the stylesheets and the browser script changed. A removed attribute and copied views to update: see
[docs/upgrading.md](docs/upgrading.md).

### Changed

- The drawer's « Filters » and the site search section's label are `<p>` elements, no longer headings: they sat
  outside the page's outline, the search sections even before its `<h1>`.
- `<x-meilifacets::listing.active-values>` renders a `<div>` holding a visually hidden « Active filters » and the
  `<ul>` of pills, instead of a `<ul>` named by `aria-label`.
- The listing search field is named by a visually hidden `<label>` instead of an `aria-label`.

### Removed

- The `heading` attribute of `<x-meilifacets::listing.drawer>`.

### Fixed

- A page no longer fails while Yoast holds no permalink for the posts page: its `wpseo_canonical` filter passes `null`
  there, which the module's canonical filter, typed `string`, refused.
- The lists styled without bullets (results, facet values, sort radios, active filters) carry `role="list"`: Safari
  dropped their list role for VoiceOver.
- A badge's count no longer inherits the letter spacing of its button, which set a lone digit off its centre.

## [0.2.3](https://github.com/Pollora/MeiliFacets/releases/tag/0.2.3) - 2026-10-16

Fifth beta. The product listing follows WooCommerce's default product sorting; past the drawer's row limit, a theme can
keep the first filters in the row and open the others from « Show more »; a listing draws its results with a card
component of the project. Reindex once (`wp meiliscout index`): `menu_order` becomes sortable. Then run
`php artisan module:publish MeiliFacets`: the browser script changed, and the old one ignores the default order once
the visitor changes page. A renamed setting and several copied views to update: see [docs/upgrading.md](docs/upgrading.md).

### Added

- `CardBinding::standalone()`: the binding of a card rendered outside a listing, such as a carousel's. Its elements
  carry no `data-meili` hook, which no client reads there and which the listing's script reported as misplaced.
- `Contracts\CardComponentListing`: a listing draws its results with a Blade component of the project, on the server
  and in the template the browser clones. The component declares `binding` and `priority`.
- A listing component inside `<x-meilifacets::listing name="…">` belongs to that listing without a `name` of its own:
  with several listings declared, only the roots need one.
- `ApplyMode::fromSetting()`: the apply mode a setting names, the default when it names none.
- `Contracts\DefaultSortedListing`: a listing orders its items while no sort is chosen and no term is searched,
  on the server and in the browser. Without it, the engine's order stands, which is the indexing order.
- A second way past the drawer's row limit: `row-overflow="show-more"` (or `meilifacets.drawer.row.overflow`) keeps
  the first filters in the desktop row and ends it with a « Show more » button that opens the drawer as the side
  sheet with the filters past them; the row stays in the page meanwhile. Its icon goes in the drawer's `show-more`
  slot. The default, `side-sheet`, is the side sheet of 0.2.2.
- `<x-meilifacets::listing.facets unwrapped>` renders the filters without their group `<div>`, so that each one wraps
  on its own in the drawer's row; `scroll` then lands on every filter, and `listing.apply` takes `scroll` too.
- `<x-meilifacets::listing.sort label="…">` names the sort, as `label` names the listing search, and the order in
  force is no longer repeated. Holding `:choice`, it replaces the sentence of the collapsible radios instead.

### Changed

- The product listing follows WooCommerce's « Default product sorting » (Appearance › Customize › WooCommerce ›
  Product Catalog) while no sort is chosen and no term is searched, instead of the indexing order, under a filter on a
  variation attribute too. `ProductListing` takes a `WooCommerceDefaultSort`: a project that builds it by hand passes
  one.
- « Popularity » and « Average rating » are no longer offered as the default product sorting: the listing cannot
  follow them yet.
- A sort that only filters, such as « On sale », keeps the listing's default order instead of the engine's.
- `components/listing/results.blade.php` draws the card a listing names. A copy of the old view fails: it passes
  `card`, which the card no longer takes.
- `<x-meilifacets::listing.card>` takes `binding` (a `CardBinding`) instead of `card`, like the card a listing names:
  `CardBinding::of($card)`. `Listing\Card::prepare()` is removed.
- `meilifacets.drawer.row_limit` becomes `meilifacets.drawer.row.limit`, beside `meilifacets.drawer.row.overflow`. The
  old key is no longer read.
- `data-apply` moves from `listing.facets` to the `<x-meilifacets::listing>` root, where it holds for every brick.
- The drawer carries `data-overflow` (`side-sheet`, `show-more`) past its row limit; `data-side-sheet` is now set by
  the client. A copied `components/listing/drawer.blade.php` fails: it calls `$isSideSheet()`, which is gone.

### Removed

- `<x-meilifacets::listing.card-template>`: the template is `<x-meilifacets::listing.card>` given
  `CardBinding::template()`.

### Fixed

- In `immediate` mode, « Clear all » appears with the active-value pills, once the engine answers, instead of on the
  tick: it no longer shows alone and then jumps aside when the first pill comes in.
- Past the row limit without JavaScript, the filters keep their row of pills instead of stacking as in the sheet.
- Reordering products by drag and drop (Products › Sorting) reindexes the products that moved: WooCommerce writes
  their `menu_order` straight to the table, which nothing reported to MeiliScout.

### Known issues

- A facet declared with `SelectionMode::Single` hides its other values once one is picked
  ([#4](https://github.com/Pollora/MeiliFacets/issues/4)).
- A failed search in the browser shows no message, and the URL and controls keep the filter that was not applied
  ([#5](https://github.com/Pollora/MeiliFacets/issues/5)).
- Saving a product re-indexes it once per meta WooCommerce writes, and each pass resends the index settings: a bulk
  edit or a catalogue import can leave the engine's queue hours behind. The fix belongs in MeiliScout.
- On a WooCommerce attribute archive (`/pa_volume/400ml/`), the card does not show the variant of the term browsed.
  Attribute archives are outside the module's scope: leave « Enable archives? » unchecked.
- A product whose variants share a term counts once per variant under that term.
- Past the row limit on a wide screen, the side sheet needs the browser client: when it does not start, the
  « Filters » or « Show more » button opens nothing.
- A theme that moves the drawer's `media` past `48em` also redeclares the side sheet block, or the drawer is drawn as a
  side sheet and driven as a bottom sheet between the two widths.
- « Popularity » and « Average rating » cannot be the listing's default order yet.

## [0.2.2](https://github.com/Pollora/MeiliFacets/releases/tag/0.2.2) - 2026-10-09

Fourth beta. Past 5 filters in the drawer when the page loads, wide screens open the filters as a side sheet behind the
« Filters » button instead of a row that pushes the results down. Requires Pollora 13.35.3 or later, which reports in
the admin when a newer MeiliFacets is available: update Pollora before the module, see
[docs/upgrading.md](docs/upgrading.md). No reindex.

### Changed

- Requires Pollora 13.35.3 or later (`pollora/framework` `>=13.35.3 <14`), which reports in Plugins › Modules, Site
  Health and `pollora:module:outdated` when a newer MeiliFacets is available.
- `Facet::isShown()` replaces `Facet::hasReadableValues()`, the name `Price` and the listing already use: a copied
  `components/listing/facet.blade.php` calls `$isShown()`.

### Added

- Past 5 filters shown when the page loads, the drawer opens as a side sheet on wide screens, behind the “Filters”
  button, instead of a row of pills that pushes the results down. The side sheet stays while the visitor filters. Set
  the limit with the drawer's `row-limit` attribute, or project-wide with `meilifacets.drawer.row_limit`; the width
  with `--meili-side-sheet-width`. Below `48em`, nothing changes. A theme that copied
  `components/listing/drawer.blade.php` copies it again to get the side sheet.

### Known issues

- A facet declared with `SelectionMode::Single` hides its other values once one is picked
  ([#4](https://github.com/Pollora/MeiliFacets/issues/4)).
- A failed search in the browser shows no message, and the URL and controls keep the filter that was not applied
  ([#5](https://github.com/Pollora/MeiliFacets/issues/5)).
- Saving a product re-indexes it once per meta WooCommerce writes, and each pass resends the index settings: a bulk
  edit or a catalogue import can leave the engine's queue hours behind. The fix belongs in MeiliScout.
- On a WooCommerce attribute archive (`/pa_volume/400ml/`), the card does not show the variant of the term browsed.
  Attribute archives are outside the module's scope: leave « Enable archives? » unchecked.
- A product whose variants share a term counts once per variant under that term.
- Past the row limit on a wide screen, the filters need the browser client: without JavaScript they stack in the page
  without their pills, and when the client does not start, the « Filters » button opens nothing.
- A theme that moves the drawer's `media` past `48em` also redeclares the side sheet block, or the drawer is drawn as a
  side sheet and driven as a bottom sheet between the two widths.

## [0.2.1](https://github.com/Pollora/MeiliFacets/releases/tag/0.2.1) - 2026-10-08

Third beta. Under Pollora 13.34.3 and later, the product listing is declared again: listings are built the first time a
page looks one up. Requires Pollora 13.35 or later: update Pollora before the module, see
[docs/upgrading.md](docs/upgrading.md). No reindex.

### Changed

- Requires Pollora 13.35 or later (`pollora/framework` `>=13.35 <14`) and `illuminate/*` `^13.35`. The listing
  discovery is bound as a singleton and added by Pollora's `DiscoveryRegistrar` (13.32 and later), as Pollora's
  documentation describes, instead of an `addDiscovery()` call in the module's provider.

### Added

- The GPL-2.0 text ships as `LICENSE`, and `package.json` declares `GPL-2.0-or-later`, as `composer.json` does.

### Fixed

- The product listing is declared again under Pollora 13.34.3 and later. Discovery now applies once per request, and
  may do so before WordPress loads its plugins: `ProductListing` was built then, refused itself without WooCommerce,
  and every listing page answered `Name the listing: none is declared`. Discovery now records the listing classes,
  and the registry builds them the first time a page asks for a listing.
- The installation guide puts the `Modules/{$name}/` rule last under `installer-paths`: `composer/installers` applies
  the first rule that matches, and placed first, the `vendor:pollora` rule also sent Pollora's WordPress plugins to
  `Modules/`.
- The requirements read Pollora 13.x from 13.4, as `composer.json` allows, instead of « 13.4 or later ».

### Known issues

- A facet declared with `SelectionMode::Single` hides its other values once one is picked
  ([#4](https://github.com/Pollora/MeiliFacets/issues/4)).
- A failed search in the browser shows no message, and the URL and controls keep the filter that was not applied
  ([#5](https://github.com/Pollora/MeiliFacets/issues/5)).
- Saving a product re-indexes it once per meta WooCommerce writes, and each pass resends the index settings: a bulk
  edit or a catalogue import can leave the engine's queue hours behind. The fix belongs in MeiliScout.
- On a WooCommerce attribute archive (`/pa_volume/400ml/`), the card does not show the variant of the term browsed.
  Attribute archives are outside the module's scope: leave « Enable archives? » unchecked.
- A product whose variants share a term counts once per variant under that term.

## [0.2.0](https://github.com/Pollora/MeiliFacets/releases/tag/0.2.0) - 2026-10-06

Second beta. Variable products are listed through their variants: a checked size ranks, prices and shows the
product by that size. Requires MeiliScout's `feat/meilifacets` branch at `ef2bd18` or later, and a full reindex: see
[docs/upgrading.md](docs/upgrading.md).

### Added

- Card variants: with WooCommerce, the module reads a variable product's variants when it is indexed
  (`Indexing\ProductVariants`, `Listing\CardVariant`): terms, price, stock, price HTML, link and own image, which
  replaces the product's image whole. When a facet on a variation attribute is checked, a listing with variant
  documents (`Contracts\VariantScopedListing`) shows the card through a matching variant (`Listing\VariantChoice`), on
  the server and in the browser alike: those in stock are preferred, then the cheapest. A price range alone keeps the
  card as projected, and a listing without variant documents always does. `several_variants` flags a card on which several variants are offered, for a « from »
  prefix; `out_of_stock` flags a card that shows a variant out of stock. With no such filter, or none matching, the
  card is shown as projected. A `variants` list a `CardProjector` returns is dropped.
- `out_of_stock` on the card of every product WooCommerce holds out of stock, whatever its type
  (`Indexing\ProductStock`).
- One document per variant of a variable product, under WooCommerce's variation post type `product_variation`,
  written and removed with the product's (`Indexing\VariantDocuments`, through MeiliScout's `HasDependentDocuments`):
  a query on `post_type = "product"` keeps reading one document per product. A filter on a variation attribute reads
  the results on them, one per product and in stock first under a price sort (`Contracts\VariantScopedListing`), and
  the counts follow; a price range alone reads the products, as WooCommerce does. The terms of a variation attribute
  are counted on the variants, which WooCommerce's attribute lookup table names (`Listing\VariationTaxonomies`). A
  product is re-indexed when one of its variations changes during a request (`Indexing\VariationChanges`). Each variant
  document carries its variation's own on-sale flag, and under the « On sale » sort a card only shows a variant on
  sale. New document fields: `in_stock`, `document_kind` (on a parent), `parent_id`. `FilterExpression::any()` joins clauses
  with `OR`.
- `Contracts\VariantFields`, which lets a project add fields to each variant; `Indexing\EmptyVariantFields` is the
  default.
- `Enums\VariantField`, which names the keys of a variant (`facets`, `price`, `fields`, `in_stock`, `on_sale`), and
  `CardField::Variants`, `CardField::SeveralVariants` and `CardField::OutOfStock`, which name the card's `variants`
  list and its two flags.
- `data-meili-class-list` and `CardBinding::classList()`: adds the classes a card field holds to the element's own,
  for classes a platform computes, such as WooCommerce's loop button classes. `CardFieldElement::with()` refuses a
  second `classList()` on one element (`BindingRefused`), which the card template would drop.
- The `meilifacets/is_listing_page` filter, which tells the module whether a page renders a listing.

### Changed

- Products and their variants default to the `woocommerce_thumbnail` image size instead of `medium`; `card.image_size`
  still sets one size for every card.

- A card rendered by the server no longer carries the binding attributes (`data-meili-text`, `data-meili-attr`,
  `data-meili-class`, `data-meili-class-list`, `data-meili-if`): only the template does, since the browser draws every
  card from a copy of it. A few hundred bytes less per card on a product card.
- With Yoast SEO, a secondary listing view (filtered, sorted, searched or paginated, still `noindex, follow`) declares
  the bare path as its canonical, page number kept, instead of none.
- The `noindex` and canonical rules, the removal of `rel="next"` and `rel="prev"`, and the preconnect hint apply on
  the pages that render `<x-meilifacets::listing>` before their `<head>`, instead of every archive and search page:
  an archive with no listing keeps its own robots and canonical.

### Fixed

- The clear button of the site search field shows its glyph centred vertically on WebKit (Safari, iOS).
- The theme override of the module's views registers through `add_action`, as Pollora 13.34 renamed the contract it
  used.
- The listing outage answers `503`, `Retry-After` and `Cache-Control: no-store` through a global middleware: Pollora
  used to send it as a public `200`.
- A facet value longer than a WordPress slug (200 bytes) is ignored instead of reaching the engine.

### Known issues

- A facet declared with `SelectionMode::Single` hides its other values once one is picked
  ([#4](https://github.com/Pollora/MeiliFacets/issues/4)).
- A failed search in the browser shows no message, and the URL and controls keep the filter that was not applied
  ([#5](https://github.com/Pollora/MeiliFacets/issues/5)).
- Saving a product re-indexes it once per meta WooCommerce writes, and each pass resends the index settings: a bulk
  edit or a catalogue import can leave the engine's queue hours behind. The fix belongs in MeiliScout.
- On a WooCommerce attribute archive (`/pa_volume/400ml/`), the card does not show the variant of the term browsed.
  Attribute archives are outside the module's scope: leave « Enable archives? » unchecked.
- A product whose variants share a term counts once per variant under that term.

## [0.1.0](https://github.com/Pollora/MeiliFacets/releases/tag/0.1.0) - 2026-10-01

First release, a **beta**: the PHP API and the markup contract may still change before 1.0, and the known issues
below are open.

### Added

- Faceted product listing served by Meilisearch: the server renders the first page with the filters in the URL
  applied, the browser queries the engine for every change after that.
- Facets by taxonomy and product attribute, price range, sorts, active filters, pagination and a mobile drawer.
- A search field inside the listing, read from and written to the `q` parameter.
- Site search panel: products and posts side by side, a count per type and a link to the full results.
- Product cards filled by attribute binding (`data-meili-text`, `data-meili-attr`, `data-meili-class`,
  `data-meili-if`), so a theme keeps its own card markup.
- Extension points behind container bindings: facets, sorts, card projection, searchable attributes and types.

### Known issues

- A facet declared with `SelectionMode::Single` hides its other values once one is picked: the visitor removes the
  current value before choosing another ([#4](https://github.com/Pollora/MeiliFacets/issues/4)).
- A failed search in the browser shows no message, and the URL and controls keep the filter that was not applied
  ([#5](https://github.com/Pollora/MeiliFacets/issues/5)).
