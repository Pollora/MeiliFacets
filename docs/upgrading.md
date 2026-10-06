# Upgrading

This page lists, for each release, what a project has to change when it updates MeiliFacets. The full list of
changes is in the [changelog](../CHANGELOG.md).

0.1.0 is the first release, a beta.

## Unreleased

- **Update MeiliScout first.** The module now implements MeiliScout's `HasDependentDocuments` and fires
  `meiliscout/reindex_post`: run `composer update amphibee/meiliscout` before updating the module, or the indexable
  fails to load.
- **Reindex, then publish; same contract.** `Contract::VERSION` is unchanged. Every listing with a facet on a variation
  attribute counts it on `document_kind` and `parent_id`, filtered or not, and the engine refuses those queries until
  the new settings are pushed: run `wp meiliscout index --clear` right after deploying the code, before it serves a
  page, or those listings answer with the outage view until it is done. It also writes one document per variant, under
  the post type `product_variation`: your own queries on `post_type = "product"` keep reading one document per product.
  Then publish the client scripts in `dist/` and the site search stylesheet with `php artisan module:publish
  MeiliFacets`, and clear the page cache: a page cached before the release sends the engine the queries of the old
  client.
- **If you projected `variants`.** A `variants` list your `CardProjector` returns is now always dropped: only the
  module's is indexed. Remove the code that built it, and move the fields of your own — a size label, a cart link —
  to a `Contracts\VariantFields` you bind. See [Card variants](customising/card.md#card-variants).
- **Reserved card fields.** `variants`, `several_variants` and `out_of_stock` belong to the module. A `variants` or
  `out_of_stock` your `CardProjector` returns is dropped, a variant's fields cannot set any of them, and whenever a
  variant is shown the module clears `several_variants` and `out_of_stock` before setting them again.
- **The default image size of a product card is WooCommerce's.** Products and their variants now default to
  `woocommerce_thumbnail` instead of `medium`; other posts keep `medium`. Set `card.image_size` to keep one size for
  every card, then reindex.
- **Binding attributes leave the cards the server renders.** `data-meili-text`, `data-meili-attr`, `data-meili-class`,
  `data-meili-class-list` and `data-meili-if` are now written in the card `<template>` only. A card rendered with its
  values keeps its values, its classes and its `data-meili` hooks. Search the theme's stylesheets and scripts for
  these attributes: a selector or a script that reads them on a rendered card now finds nothing. Target the card's
  own classes or its `data-meili` hook instead.
- **The canonical of a secondary listing view.** A filtered, sorted, searched or paginated view, still served
  `noindex, follow`, now declares the bare path as its canonical, page number kept, instead of none. Nothing to
  change in a project; watch the indexing status of the bare paths in your Search Console after the release.

## Before any upgrade

- **Overridden views.** A view copied into the theme keeps its old markup when the module changes its own. Compare
  each overridden view with the module's new version.
- **The markup contract.** The listing and the site search check the `data-meili` hooks they need when they start,
  and the root element states the contract version it was rendered for (`data-meili-contract`). When the version
  changes, the client refuses older markup and names what is missing in the browser console. A release that changes
  the contract says so in this page.
- **The index.** A release that changes what is indexed asks for a full reindex: `wp meiliscout index --clear`.
- **Published assets.** Copy the new stylesheet and client into `public/` with
  `php artisan module:publish MeiliFacets`, then clear the page cache.
