# Changelog

All notable changes to MeiliFacets are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

What to change in a project when moving from one version to the next is in
[docs/upgrading.md](docs/upgrading.md).

## [Unreleased]

### Added

- `data-meili-class-list` and `CardBinding::classList()`: adds the classes a card field holds to the element's own,
  for classes a platform computes, such as WooCommerce's loop button classes.

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
