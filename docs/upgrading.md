# Upgrading

This page lists, for each release, what a project has to change when it updates MeiliFacets. The full list of
changes is in the [changelog](../CHANGELOG.md).

No version has been released yet, so there is nothing to upgrade from.

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
