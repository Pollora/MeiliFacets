# Changelog

All notable changes to MeiliFacets are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

What to change in a project when moving from one version to the next is in
[docs/upgrading.md](docs/upgrading.md).

## [Unreleased]

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
