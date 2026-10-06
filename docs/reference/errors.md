# Errors and console messages

Every message the module can show you: exceptions thrown while a page renders, failures it logs without breaking
the page, browser console lines, and the failures that say nothing at all.

You want to…

- [fix an exception about a listing or search name](#listing-and-search-names);
- [fix an exception thrown by a listing component](#listing-rendering);
- [fix an exception thrown by the site search](#search-rendering);
- [fix an exception about a card binding or an attribute](#card-binding-and-attributes);
- [understand a failure written to the log](#reported-without-breaking-the-page);
- [understand a `[meilifacets]` line in the browser console](#browser-console);
- [find a failure that shows no message](#silent-failures).

```text
RuntimeException: Name the listing: 2 are declared (products, events).
```

```blade
<x-meilifacets::listing name="products">
```

Placeholders below are written `<like-this>`. Messages from the console commands are listed under
[Commands](commands.md).

## Listing and search names

Thrown while the page renders: the page fails with the exception.

| Exception | Message | When | Fix |
| --- | --- | --- | --- |
| `RuntimeException` | `No listing named "<name>". Declared: <names>.` | a component's `name` matches no declared listing | use a declared name; run `php artisan discovery:clear` after adding a `Listing` class |
| `RuntimeException` | `Name the listing: none is declared.` | a component names no listing and none is declared: no WooCommerce for the product listing, or a listing whose constructor threw | activate WooCommerce or declare a `Listing`; read the log for a reported failure |
| `RuntimeException` | `Name the listing: <n> are declared (<names>).` | a component names no listing and several are declared | give every component of the page its listing's `name` |
| `RuntimeException` | `No search named "<name>". Declared: <names>.` | a search brick's `name` matches no `<x-meilifacets::search>` rendered before it | name the brick after its root, and place it inside |
| `RuntimeException` | `Name the search: none is declared.` | a search brick renders with no root before it | place the brick inside `<x-meilifacets::search>` |
| `RuntimeException` | `Name the search: <n> are declared (<names>).` | a search brick names no root and the page holds several | give the brick its root's `name` |
| `LogicException` | `A search named "<name>" is already rendered on this page: give each root its own name.` | two `<x-meilifacets::search>` share a name (default `search`) | give each root its own `name` |

Two listings declaring the same name do not throw: the last one discovered replaces the other.

## Listing rendering

| Exception | Message | When | Fix |
| --- | --- | --- | --- |
| `RuntimeException` | ``Facets "<label>" and "<label>" answer to the same name "<name>" in listing "<listing>". Declare `name:` on all but one of them.`` | two declared filters share a `name` (two facets of one taxonomy, or a facet named `price`) | pass `name:` to one of them |
| `RuntimeException` | `No facet named "<name>" in listing "<listing>". Declared: <names>.` | `facet` on `listing.facet` or `listing.price` names no declared filter | use a declared name, or the enum case holding it |
| `RuntimeException` | `"<name>" in listing "<listing>" is a <Kind>: place it with the component of its own kind.` | a `PriceFilter` given to `listing.facet`, or a `Facet` given to `listing.price` | `<x-meilifacets::listing.price>` for a `PriceFilter`, `<x-meilifacets::listing.facet>` for a `Facet` |
| `RuntimeException` | `Facet "<name>" is rendered twice on this page: its inputs and ids would be duplicated. Place it on its own before <x-meilifacets::listing.facets>, which shows what is left.` | the same facet placed twice, or placed on its own after the group | place it once, before the group |
| `RuntimeException` | `The sort of listing "<listing>" is rendered twice on this page: its list and ids would be duplicated. Render <x-meilifacets::listing.sort> once per page.` | two sort components for one listing | keep one |
| `InvalidArgumentException` | `Facet "<name>" holds one value at a time, so its values cannot be presented as "<presentation>": they would be radios, which cannot be unchecked. Present it as "control", or declare it with SelectionMode::Multiple.` | a single-value facet declared or rendered with a presentation that hides its radios (`pill`) | use `control`, or `SelectionMode::Multiple` |

## Search rendering

| Exception | Message | When | Fix |
| --- | --- | --- | --- |
| `SearchTypeRefused` | `Post type "<type>" is not searchable: declare it in SearchableTypes and index it in MeiliScout. Searchable here: <types>.` | a section's `type` is not among the searchable types | index the type in MeiliScout, make it public and searchable, or declare it in `SearchableTypes` |
| `SearchTypeRefused` | `Post type "<type>" is not registered: WordPress has no labels, archive or taxonomies for it.` | `SearchableTypeFactory` is asked for an unregistered post type | register the post type, or remove it |
| `LogicException` | `The search "<name>" already holds a section for "<type>": place one section per type.` | two sections of one type in one root | keep one |
| `LogicException` | `The search section for "<type>" asks for <limit> results: it shows a whole number of them, at least 1.` | `limit` is not a whole number of at least `1` | `limit="6"` or `:limit="6"` |
| `NoFieldToSearch` | `Post type "<type>" has no field to search: the search order (SearchableAttributes) keeps none of the fields it is searched on. Keep one of them in the order, or remove the type from SearchableTypes.` | a type's `searchOn` is empty | keep one of its fields in `SearchableAttributes` |
| `UnsearchableFields` | `Post type "<type>" is searched on fields the index does not search: <fields>. Meilisearch refuses the whole query: add them to SearchableAttributes or remove them from searchOn.` | `withSearchOn()` names fields outside the search order | add them to `SearchableAttributes`, or remove them |

`SearchTypeRefused`, `NoFieldToSearch` and `UnsearchableFields` live in `Modules\MeiliFacets\SiteSearch` and
extend `LogicException`.

## Card binding and attributes

| Exception | Message | When | Fix |
| --- | --- | --- | --- |
| `BindingRefused` | `Attribute "<name>" cannot be bound to a card field: only href, src, srcset, sizes, alt, title, width, height, value, datetime, aria-*, data-* can, data-meili* excepted. Write classes with classes(), and anything else in the view.` | `$binding->attributes()` in a card view names another attribute | bind an allowed attribute; write the rest in the view |
| `BindingRefused` | `Card field "<field>" cannot be bound: a field name is made of letters, digits and underscores only.` | a field name with other characters | rename the field |
| `BindingRefused` | `An element takes one classList(): the card template would keep only the first field. Bind the second on a child element.` | `->with()` gives an element a second `classList()` | bind the second field on a child element |
| `ValueError` | `"<value>" is not a valid backing value for enum Modules\MeiliFacets\Enums\<Enum>` | an unknown string for `heading`, `shape`, `widget` or `presentation` | use a listed value (see [components](components.md)) |
| `TypeError` | PHP's message for a wrong argument type | `priority` on `listing.card` given as a string | bind an `ImagePriority` case |

`BindingRefused` lives in `Modules\MeiliFacets\View` and extends `LogicException`.

## Reported without breaking the page

Sent to Laravel's `report()`, so they reach your log and error tracker; the page is still served.

| Exception | Message | When | What the visitor gets | Fix |
| --- | --- | --- | --- | --- |
| `EngineUnavailable` | `Meilisearch did not answer. Check MEILI_HOST and that the engine is running.` | the engine is down, unreachable, or refused the query (an attribute not filterable or sortable yet) | “Search is temporarily unavailable. Please try again in a moment.”, HTTP `503`, `Retry-After: 120`, `Cache-Control: no-store` | start the engine; check `MEILI_HOST`; reindex so the settings are written |
| `EngineUnavailable` | `Meilisearch answered <n> of <m> searches sent together.` | the engine answered fewer searches than sent | same | check the engine's version and logs |
| `EngineUnavailable` | `No Meilisearch client. Check MEILI_HOST and MEILI_SEARCH_KEY.` | MeiliScout could not build its search client | same | set `MEILI_HOST` and `MEILI_SEARCH_KEY` |
| `FacetTruncated` | `Facet "<taxonomy>" returned <n> values, the ceiling the engine applies. Values beyond it were dropped before the facet could narrow them. Raise meilifacets.engine.max_facet_values, then reindex to push the setting. A taxonomy that happens to use exactly that many values reports this too.` | a facet received `engine.max_facet_values` values, or `100` (the engine's default before the first reindex) | the facet, missing the values past the ceiling | raise `engine.max_facet_values`, reindex |
| any | the listing's own exception | a `Listing` class threw while discovery built it (other than `ListingUnavailable`) | no such listing: “Name the listing…” on the page | fix the listing or its bindings |

`EngineUnavailable` lives in `Modules\MeiliFacets\Search`, `FacetTruncated` too; both extend `RuntimeException`.
A listing constructor that throws `ListingUnavailable` opts out without any report: the product listing does so
with `WooCommerce is not active: there are no products to list.`

## Browser console

Written with `console.error`, prefixed `[meilifacets]`. The page keeps the markup the server rendered.

| Message | When | Fix |
| --- | --- | --- |
| `[meilifacets] the markup does not meet the contract: <breaches>` | a required hook is missing (listed as `hook` or `host > hook`); the root's version differs (`contract <version>, expected 1`, `contract absent, expected 1`); the root carries neither attribute (`no root: expected one of [data-listing], [data-search]`) | restore the hooks named; republish the assets if the version differs. See [hooks](hooks.md) |
| `[meilifacets] the client binds inside [data-listing] only. Move inside <x-meilifacets::listing> : <hooks>.` | listing hooks outside every listing root | move the bricks inside the root |
| `[meilifacets] the client binds inside [data-search] only. Move inside <x-meilifacets::search> : <hooks>.` | search hooks outside every search root | move the bricks inside the root |
| `[meilifacets] no data was published under @meilifacets/listing.` | a listing root is on the page but WordPress printed no data for its bundle | republish the assets; check that the page calls `wp_footer()` |
| `[meilifacets] no data was published under @meilifacets/site-search.` | same, for the search | same |
| `[meilifacets] the page describes no listing named "<name>".` | the root's `data-listing` does not match what the server described | keep `data-listing` as the root view renders it |
| `[meilifacets] the page describes no search named "<name>".` | same, for `data-search` | keep `data-search` as rendered |
| `[meilifacets] the search "<name>" has no <input> to read.` | `search-input` is not an `<input>` | keep an `<input>` on the hook |
| `[meilifacets] the search "<name>" describes no type "<type>".` | a section's `data-type` names no type of its root | keep `data-type` as rendered |
| `[meilifacets] the search failed: <error>` | the engine refused or did not answer within 5 seconds | the panel shows “Search unavailable”; check the browser connection and the key |
| `[meilifacets] the search client could not be loaded: <error>` | `site-search-client.js` failed to load | republish the assets; check the cache |
| `Error: [meilifacets] the contract root is not in a window.` | a root detached from any document | not reachable on a normal page |

## Silent failures

No exception, no log line, no console message.

| Symptom | Cause | Fix |
| --- | --- | --- |
| The listing renders, filters do nothing; the magnifier opens nothing | `browser.url` or `browser.key` is empty, or `browser.url` has no scheme: no script is loaded | set both, with the scheme. See [configuration](configuration.md) |
| Same, and elements hidden by the client show | the bundles are not published under `public/modules/meilifacets` | `php artisan module:publish MeiliFacets` |
| Odd behaviour after a deployment | published assets older than the module's | `php artisan meilifacets:check-assets` |
| Zero results, the engine answers | the index is empty | `wp meiliscout index` |
| A listing search in the browser fails | the engine refused or timed out after the first render | the results stay as they were, with no message |
| A component's `class` is ignored | the component drops its attribute bag | see [components](components.md#watch-out) |

## See also

- [Troubleshooting and FAQ](../troubleshooting.md)
- [Commands](commands.md)
- [`data-meili` hooks](hooks.md)
