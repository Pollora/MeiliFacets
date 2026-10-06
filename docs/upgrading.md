# Upgrading

This page lists, for each release, what a project has to change when it updates MeiliFacets. The full list of
changes is in the [changelog](../CHANGELOG.md).

0.1.0 is the first release, a beta.

## Unreleased

- **Nothing to reindex, same contract.** `Contract::VERSION` is unchanged and the indexed documents keep their shape.
  The client scripts in `dist/` and the site search stylesheet changed: publish them with
  `php artisan module:publish MeiliFacets`, then clear the page cache.
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
