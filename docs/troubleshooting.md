# Troubleshooting and FAQ

This page lists the failures met when installing and running the module, by symptom, with their cause and their fix,
followed by common questions.

You want to…

- [fix an installation problem](#installation);
- [fix a listing that renders but does not filter](#the-listing-does-not-filter);
- [fix a page that is wrong after a deployment](#after-a-deployment);
- [fix « Search is temporarily unavailable »](#search-is-temporarily-unavailable);
- [fix wrong or missing results](#results-and-facets);
- [fix wrong prices](#prices);
- [fix a site search that does nothing](#the-site-search-does-nothing);
- [fix an ignored override](#overrides);
- [know what the module does not report](#known-limitations);
- [read the FAQ](#faq).

Start with the browser console and the application log. Most failures leave a line in one of them; the ones that do
not are listed in [Errors and console messages](reference/errors.md#silent-failures).

```text
[meilifacets] the markup does not meet the contract: contract absent, expected 1
```

## Installation

| Symptom | Cause | Fix |
| --- | --- | --- |
| The package is installed in `libraries/MeiliFacets/`, and `module:list` does not show it | the project's `composer.json` has no `installer-paths` rule for the `pollora` vendor | add `"Modules/{$name}/": ["vendor:pollora"]` as the last rule under `extra.installer-paths`, then `composer remove` and `composer require` the package again. See [Installation](installation.md#prepare-composerjson) |
| WordPress core (`public/cms`) disappears after a `composer install` | the merge plugin merges the module's `require-dev` | set `"merge-dev": false` under `extra.merge-plugin`, then `composer install` again |
| `composer require` fails with « does not match your minimum-stability » on `amphibee/meiliscout` | MeiliScout is required at a development branch by the module only | require it in the project too: `composer require pollora/meilifacets:^0.2 amphibee/meiliscout:dev-feat/meilifacets -W` |
| `php artisan module:list` does not show MeiliFacets, or shows it disabled | `modules_statuses.json` is missing or does not enable it | write `{"MeiliFacets": true}` in `modules_statuses.json` at the project root |
| `php artisan list` shows no `meilifacets:` command | the module is not loaded | same as the two rows above; then `composer dump-autoload` |
| `Unable to locate a class or view for component [meilifacets::listing]` | the module is not loaded | same |
| `Name the listing: none is declared.` | WooCommerce is not active, or a `Listing` class threw while being built | activate WooCommerce; read the log for a reported exception |

## The listing does not filter

The page renders, the boxes can be checked, and nothing happens.

| Cause | How to tell | Fix |
| --- | --- | --- |
| `browser.url` or `browser.key` is empty, or `browser.url` has no scheme | no `listing.js` in the page's scripts; no message anywhere | set both in `config/meilifacets.php`, with `https://`. See [Installation](installation.md#publish-the-configuration-file) |
| the assets are not published | `public/modules/meilifacets` is missing; elements the client normally hides are visible | `php artisan module:publish MeiliFacets` |
| the component sits outside `<x-meilifacets::listing>` | `[meilifacets] the client binds inside [data-listing] only…` in the console | move it inside the root |
| an overridden view lost a `data-meili` hook, or the root's contract version differs | `[meilifacets] the markup does not meet the contract: …` in the console, naming each breach | restore the hooks named. See [Overriding views](customising/views.md#when-the-contract-is-not-met) |
| the page never calls `wp_footer()` | `[meilifacets] no data was published under @meilifacets/listing.` | call `wp_footer()` in the layout |
| the page is opened over `http` while WordPress declares `https` | the browser refuses the scripts as another origin, the theme's too | open the site over the scheme of `home` and `siteurl` |
| the engine refuses the browser's key | `403` or `401` from the engine in the network panel | use a search-only key that can search `posts`. See [Going to production](production.md#the-search-key) |

When the client does not start, **mobile filters are out of reach**. Below `48em`, the stylesheet hides the drawer as
soon as scripting is enabled, whether the client loaded or not. The « Filters » button then opens nothing. Fix the
client first; see [Mobile drawer and filter bar](listing/drawer.md#watch-out).

## After a deployment

| Symptom | Cause | Fix |
| --- | --- | --- |
| The page looks right but behaves oddly, a range bar paints wrong, a control does nothing | the published assets are older than the module's | `php artisan module:publish MeiliFacets`, then `php artisan meilifacets:check-assets` |
| Republished, and still the old behaviour | a page cache or a minified copy of the assets | clear the page cache and the minified files |
| A new `Listing` class is not found: `No listing named "…"` | Pollora's discovery cache | `php artisan discovery:clear` |

## Search is temporarily unavailable

The whole listing is replaced by « Search is temporarily unavailable. Please try again in a moment. », and the page is
served with status `503`. The log holds an `EngineUnavailable` report.

| Log message | Cause | Fix |
| --- | --- | --- |
| `Meilisearch did not answer. Check MEILI_HOST and that the engine is running.` | the engine is down or unreachable from PHP | start it; check `MEILI_HOST` from where PHP runs |
| same | the query uses a field the index does not declare filterable or sortable yet: a new facet, a new sort | `wp meiliscout index`, which writes the index settings. See [Going to production](production.md#when-a-deployment-needs-a-reindex) |
| `No Meilisearch client. Check MEILI_HOST and MEILI_SEARCH_KEY.` | MeiliScout could not build its search client | set `MEILI_HOST` and `MEILI_SEARCH_KEY` |

## Results and facets

| Symptom | Cause | Fix |
| --- | --- | --- |
| Zero results, and the engine answers | the index is empty | `curl …/stats` returns `{"indexes":{}}`: run `wp meiliscout index`. See [Installation](installation.md#check-that-it-works) |
| A facet is missing or always empty | its taxonomy is not indexed in MeiliScout, or the settings were not pushed | select the taxonomy in MeiliScout's admin screen, then `wp meiliscout index`. See [Facets](listing/facets.md) |
| A facet misses values on a large taxonomy | the engine's ceiling on facet values; the log holds a `FacetTruncated` report | raise `engine.max_facet_values`, then reindex |
| A facet does not show on a category archive | the path already filters on that taxonomy | expected; use a `ChildTermsFacet` for sub-levels. See [Facets](listing/facets.md#sub-level-navigation) |
| A card's image is outdated after editing the image | the image fields are stored at indexing time | `wp meiliscout index` after `wp media regenerate` |
| `?s=!` or `?q=%3F` shows the whole catalogue | a term with no letter or digit counts as no term | by design: WordPress itself would show nothing |

## Prices

| Symptom | Cause | Fix |
| --- | --- | --- |
| Prices or the price filter ignore a tax change | the indexed price is the displayed one, computed at indexing | reindex. See [Indexed prices](indexing/prices.md#taxes) |
| A scheduled sale is not applied | cron does not run, so Action Scheduler never switches the price | run cron. See [Going to production](production.md#cron-must-run) |
| A WooCommerce price widget disagrees with the listing | WooCommerce's own widgets read `min_price` and `max_price` | use `<x-meilifacets::listing.price>`. See [Indexed prices](indexing/prices.md) |

## The site search does nothing

The magnifier renders, and clicking it opens nothing, or the panel never shows results. The causes are the same as
for the listing: browser connection, publication, contract. The search client loads on the first hover or focus of
the magnifier; look for `[meilifacets] the search client could not be loaded` or `the search failed` in the console.
The panel shows « Search unavailable » when the engine refuses or does not answer within 5 seconds. See
[Site search](search/README.md).

## Overrides

| Symptom | Cause | Fix |
| --- | --- | --- |
| An override is ignored | the file is not under `<theme>/resources/views/modules/meilifacets/components/` with the module's own path | copy the module's path exactly, for example `components/listing/facet.blade.php`. See [Overriding views](customising/views.md#the-cascade) |
| A `class` given to a component is lost | `listing.results`, `listing.facets`, `listing.sort`, `listing.pagination`, `listing.reset` and `listing.active-filters` take no attribute bag | wrap the component, or override its view |
| A token set on `:root` has no effect | the defaults are declared on the component roots | set it on `body [data-listing]` or `body [data-meili="search"]`. See [Styles and design tokens](customising/styles.md#where-to-set-the-tokens) |
| A filtered URL is not `noindex` | the layout prints the `<head>` before it renders the listing | return `true` from the `meilifacets/is_listing_page` filter on that page; see [How a listing works](listing/README.md#what-search-engines-see) |

## Known limitations

- **A failed search in the browser shows no message.** When the engine refuses a listing query sent by the browser,
  or does not answer within 5 seconds, the grid keeps its previous results while the URL and the controls already show
  the requested filter, and nothing is announced. The console logs nothing for the listing either. The first render,
  served by PHP, does show the « unavailable » message. [Issue #5](https://github.com/Pollora/MeiliFacets/issues/5)
  tracks the fix.
- **Without JavaScript, only the first render and the listing search field work.** Facets, sorting and pagination
  are buttons and need the client.
- **The browser client's timeout** (5 seconds) is not configurable.

## FAQ

**Can I use it without WooCommerce?**
Yes. The site search works on any indexed public post type. Without WooCommerce there is no product listing:
declare your own by implementing `Listing`. See [Listing other content](listing/custom-listing.md).

**Does the search key leak data?** It can read `ID`, `card` and `parent_id` from every document of the `posts` index,
and can rewrite a listing's base filter. Keep only published content in the index and use a search-only key. See [Going
to production](production.md#security).

**Why are the filters not in a `<form>`?**
Each gesture is a request from the browser to the engine; nothing is submitted to WordPress. The listing search field
is a real form, so it works without JavaScript.

**Can I have two listings on one page?**
Yes, if each one is declared with its own name and every component names its listing. See
[Listing other content](listing/custom-listing.md#two-listings-on-one-page).

**Can I change the `48em` breakpoint?**
Yes, but the stylesheets write it in their media queries: redeclare the rules concerned in the theme, and pass the
same value to the drawer's `media` attribute. See [Styles and design tokens](customising/styles.md#the-breakpoint).

**Why is a key I set in the module's own `config/config.php` ignored?**
Set it in the project's `config/meilifacets.php`. The module's own file is merged over the project's and only holds
what a project must not change. Never edit the installed module: `composer update` replaces it.

**Why does a new card field not show?**
Documents keep what was computed when they were indexed. Reindex after changing the card. See
[What gets indexed](indexing/README.md#when-to-reindex).

## See also

- [Errors and console messages](reference/errors.md)
- [Installation](installation.md)
- [Going to production](production.md)
- [Commands](reference/commands.md)
