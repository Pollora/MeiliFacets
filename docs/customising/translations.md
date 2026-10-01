# Translating the interface

This page covers how the module's interface strings are translated, how a theme rewords them, and how to add a
language.

You want to…

- [know how the strings are written](#how-strings-are-written);
- [know which languages ship with the module](#shipped-languages);
- [reword a label from your theme](#rewording-from-the-theme);
- [add a language](#adding-a-language);
- [translate a count or a sentence with a placeholder](#counts-and-placeholders);
- [find every key](#every-key);
- [translate the labels that do not come from the module](#labels-that-come-from-elsewhere).

To change « Clear all » into « Reset » on an English site, put the key in your project's catalogue:

```json
{
    "Clear all": "Reset"
}
```

```text
lang/en.json
```

## How strings are written

Every string of the module is written in English, in Laravel's `__()` helper, **without a text domain**:
`__('Clear all')`. The English sentence is the key. Translations live in Laravel JSON catalogues, one file per
language, that map each English key to its translation.

A key that no catalogue holds is shown as is, in English. Nothing breaks, and nothing warns either.

On Pollora, `__()` looks a key up in the Laravel catalogues for the site's WordPress locale (`fr_FR`), then for its
language alone (`fr`), then in WordPress's own translations. A key missing from every catalogue can therefore come out
translated when WordPress core happens to hold the same English sentence (« Search », « Next »…).

## Shipped languages

The module ships one catalogue: `lang/fr.json`, French. Any other language is yours to add.

## Rewording from the theme

Laravel reads every registered catalogue and, for a key found in several, keeps the last one read. Three places come
after the module's:

| Catalogue | Wins over | Registration |
| --- | --- | --- |
| `lang/<locale>.json` at the project root | the module and the theme | none |
| a theme catalogue, such as `<theme>/languages/<locale>.json` | the module | see below |
| `resources/lang/modules/meilifacets/<locale>.json` in the project | it **replaces** the module's catalogue | none |

The project root catalogue is the simplest: it is always read, and wins.

A theme catalogue must be registered, and on Pollora registering it is not enough. Laravel has already loaded and
cached the catalogues by the time WordPress loads the theme, and it never reads a cached language again. Clear the
cache right after adding the path, for example in the theme's `functions.php`:

```php
$translator = app('translator');
$translator->addJsonPath(__DIR__.'/languages');
$translator->setLoaded([]);
```

The third place is a full replacement: when the folder `resources/lang/modules/meilifacets/` exists in the project,
the module reads its catalogues there **instead of** its own `lang/` folder. Every key you leave out falls back to
English. Use it only to own the whole translation.

## Adding a language

1. Copy the module's `lang/fr.json` and name the copy after the language: `de.json`, `es.json`, or a locale such as
   `pt_BR.json` when the language varies by region.
2. Translate every value. Keep every key unchanged, and keep the placeholders (`:count`, `:choice`…) in each value.
3. Put the file in your project's `lang/` folder or in a registered theme catalogue (see above).
4. Clear your page cache: the counts and sentences the browser writes are published in the page.

The browser client needs no translation file: the server translates the patterns it needs and publishes them with the
page, with the site's language tag for plural rules.

## Counts and placeholders

A placeholder starts with a colon and is replaced at render: keep it, and move it where your language puts it.

```json
{
    "Sort by: :choice": "Trier par : :choice",
    "Remove the :label filter": "Retirer le filtre :label",
    ":min – :max": "Entre :min et :max"
}
```

`Sort by: :choice` is split around `:choice`: the words before and after it, and the sort's name, are three separate
nodes. The name can sit at the start, the middle or the end of your sentence.

A count is a pattern with two forms, separated by `|`: the singular, then the plural.

```json
{
    ":count result|:count results": ":count résultat|:count résultats"
}
```

The form is chosen by the plural rules of the site's language (CLDR, through PHP's `intl` extension on the server and
`Intl.PluralRules` in the browser): the first form for the « one » category, the second for everything else. In French,
`0` and `1` take the first form.

## Every key

| Key | Shown |
| --- | --- |
| `:count active filter\|:count active filters` | the active filters count (`<x-meilifacets::listing.active-filters>`) |
| `:count item\|:count items` | the listing total |
| `:count result\|:count results` | a facet value's count, a search section's count |
| `:heading: :count` | the sentence announced per search section, such as « Products: 3 results » |
| `:min – :max` | an active price filter with both bounds |
| `From :min` | an active price filter with a lower bound only |
| `Up to :max` | an active price filter with an upper bound only |
| `“:query”` | an active search term |
| `Remove the :label filter` | the accessible name of an active filter's button |
| `Active filters` | the accessible name of the list of active filters |
| `Apply` | the « Apply » pill |
| `Apply filters` | the « Apply » block under the facets |
| `Clear all` | the reset button, and its accessible name in its icon shape |
| `Filters` | the drawer opener and the drawer's title |
| `Close the filters` | the accessible name of the drawer's close button |
| `Show more` | the button that unfolds a facet |
| `Show less` | the same button, unfolded |
| `From` | the lower price field |
| `To` | the upper price field |
| `Lowest price` | the accessible name of the lower price handle |
| `Highest price` | the accessible name of the upper price handle |
| `Sort by` | the sort label |
| `Sort by: :choice` | the sort trigger in its radio shape, with the sort in use |
| `Relevance` | the engine's own order, first in the sort menu |
| `Price, low to high` | a default WooCommerce sort |
| `Price, high to low` | a default WooCommerce sort |
| `New arrivals` | a default WooCommerce sort |
| `On sale` | a default WooCommerce sort |
| `Category` | the default category facet |
| `Brand` | the default brand facet |
| `Previous` | the pagination's previous button |
| `Next` | the pagination's next button |
| `Pagination` | the accessible name of the pagination |
| `No results found.` | the listing, when nothing matches |
| `There is nothing on this page.` | the listing, on a page past the last one |
| `Search is temporarily unavailable. Please try again in a moment.` | the listing, when the engine cannot be reached |
| `Search this list` | the listing search field's label and placeholder |
| `Clear the search` | the accessible name of the listing search field's clear button |
| `Search` | the site search toggle and field's accessible name |
| `Nothing matches your search` | the site search, when no section found anything |
| `Search unavailable` | the site search, when the engine cannot be reached |

## Labels that come from elsewhere

Some labels are not the module's strings:

- **Facet, sort and price filter labels** are the ones your project declares. Declare them with `__()` and no text
  domain, as the module does (`new Facet('pa_color', __('Colour'))`), and translate them in your catalogue.
- **Facet values** are the names of the WordPress terms, as editors wrote them.
- **Site search section headings** come from each post type's plural name (`labels->name`) and **« See all » links**
  from its `labels->all_items`, both translated by whatever translates the post type. To change one, decorate
  `SearchableTypes` and call `withHeading()` or `withSeeAllLabel()` (see [Searchable content types](../search/types.md)).
- **Prices** are formatted by WooCommerce at indexing time, with the shop's currency settings.
- **The price readout above the slider** joins its two amounts with an en dash (`10 € – 50 €`). It is not a key, and
  cannot be translated: the browser rewrites it with the same dash whenever a handle moves.

## Watch out

- **Only two plural forms.** A language with more plural categories (Polish, Russian, Arabic…) gets the first form
  for « one » and the second for every other category. Laravel's range syntax (`{0} None|[1,*] :count`) is not
  supported in these patterns.
- **Escape non-breaking spaces as ` `** in JSON if your language puts one before a colon or inside quotes, as
  the French catalogue does. A plain space can wrap at the end of a line.
- **The page cache keeps the old words.** The patterns are published in the page: clear the cache after changing a
  catalogue.
- **A theme catalogue without `setLoaded([])`** is never read, and no error says so. Check with a key that exists only
  in that catalogue.

## See also

- [Blade components](../reference/components.md)
- [Overriding views](views.md)
- [PHP extension points](php.md)
- [Searchable content types](../search/types.md)
