# Changelog

All notable changes to MeiliFacets are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

What to change in a project when moving from one version to the next is in
[docs/upgrading.md](docs/upgrading.md).

## [Unreleased]

### Added

- Card variants: with WooCommerce, the module reads a variable product's variants when it is indexed
  (`Indexing\ProductVariants`, `Listing\CardVariant`): terms, price, stock, price HTML, link and own image, which
  replaces the product's image whole. When a facet on a variation attribute is ticked, a listing with variant
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
  product is re-indexed when one of its variations changes during a request (`Indexing\VariationChanges`). New
  document fields: `in_stock`, `document_kind` (on a parent), `parent_id`. `FilterExpression::any()` joins clauses
  with `OR`.
- `Contracts\VariantFields`, which lets a project add fields to each variant; `Indexing\EmptyVariantFields` is the
  default.
- `Enums\VariantField`, which names the keys of a variant (`facets`, `price`, `fields`, `in_stock`), and
  `CardField::Variants`, `CardField::SeveralVariants` and `CardField::OutOfStock`, which name the card's `variants`
  list and its two flags.
- `data-meili-class-list` and `CardBinding::classList()`: adds the classes a card field holds to the element's own,
  for classes a platform computes, such as WooCommerce's loop button classes.

### Changed

- Products and their variants default to the `woocommerce_thumbnail` image size instead of `medium`; `card.image_size`
  still sets one size for every card.

- A card rendered by the server no longer carries the binding attributes (`data-meili-text`, `data-meili-attr`,
  `data-meili-class`, `data-meili-class-list`, `data-meili-if`): only the template does, since the browser draws every
  card from a copy of it. A few hundred bytes less per card on a product card.
- With Yoast SEO, a secondary listing view (filtered, sorted, searched or paginated, still `noindex, follow`) declares
  the bare path as its canonical, page number kept, instead of none.

### Fixed

- The clear button of the site search field shows its glyph centred vertically on WebKit (Safari, iOS).

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
