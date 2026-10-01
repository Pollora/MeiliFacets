# Searchable content types

This page covers the `SearchableTypes` extension point: which content types the site search can query, how each one
is labelled, which card it uses and which fields it is searched on.

You want to…

- [know where the default types come from](#where-types-come-from);
- [rename a section heading or its « see all » label](#decorating-the-default);
- [add or remove a content type](#adding-and-removing-a-type);
- [send « see all » somewhere else, or nowhere](#the-see-all-link);
- [use a different card for one type](#a-card-per-type);
- [search a type on other fields](#fields-searched).

## Where types come from

A type is searchable by default when all three are true:

- MeiliScout indexes it (its indexed post types setting);
- it is `public`;
- it is not `exclude_from_search`.

A custom post type that meets these conditions appears in the search without a line of code. With WooCommerce active,
`product` comes first; without WooCommerce, there is no product section, even if another plugin registers a `product`
type. The other types follow the order of MeiliScout's setting.

Each type is described by a `SearchableType`, built from WordPress:

| Property | Default |
| --- | --- |
| `postType` | the post type name |
| `heading` | the type's `labels->name` |
| `seeAllLabel` | the type's `labels->all_items` |
| `archive` | `get_post_type_archive_link()`, or `null` when the type has no archive |
| `baseFilter` | published documents of that type; for products, also without products hidden from search |
| `searchOn` | the title, the labels of the type's taxonomies and the excerpt; for products, the title, brand, category and SKU — in both cases, only the fields the index searches, in its ranking |
| `card` | `meilifacets::search.card` |

No type searches `content` by default: it is in the index's search order, but not in any type's default `searchOn`.

**The `product` type also drives the shop listing.** When the product listing searches a term, typed in its search
field or arriving in `?q=`, it uses the `product` type's base filter and `searchOn`. Changing them changes the shop
listing's search too.

A section placed in a template must name a type that is both declared in `SearchableTypes` and indexed by MeiliScout.
A type that is declared but not indexed is left out of the default composition, and refused by a section that asks for
it.

## Decorating the default

Bind your own `SearchableTypes` that receives the module's default and changes what it needs. Every `with…()` method
of `SearchableType` returns a new object:

| Method | Changes |
| --- | --- |
| `withHeading(string $heading)` | the section heading |
| `withSeeAllLabel(string $seeAllLabel)` | the text of the « see all » link |
| `withArchive(string $archive)` | where « see all » leads |
| `withoutArchive()` | removes the « see all » link |
| `withCard(string $card)` | the Blade component that renders a result |
| `withSearchOn(array $searchOn)` | the fields the type is searched on |

To rename the posts section:

```php
<?php

declare(strict_types=1);

namespace App\Search;

use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\SiteSearch\WooCommerceSearchableTypes;

final readonly class RetitledSearchableTypes implements SearchableTypes
{
    public function __construct(private WooCommerceSearchableTypes $default) {}

    public function all(): array
    {
        $types = $this->default->all();

        if (isset($types['post'])) {
            $types['post'] = $types['post']
                ->withHeading(__('News'))
                ->withSeeAllLabel(__('All the news'));
        }

        return $types;
    }
}
```

```php
// In a service provider of your project
use App\Search\RetitledSearchableTypes;
use Modules\MeiliFacets\Contracts\SearchableTypes;

public function register(): void
{
    $this->app->scoped(SearchableTypes::class, RetitledSearchableTypes::class);
}
```

Start from `WooCommerceSearchableTypes` whether WooCommerce is installed or not: it returns the WordPress types alone
when the plugin is inactive. Bind with `scoped` or `bind`, never `scopedIf`: the module binds its default with
`scopedIf`, so a `scopedIf` of yours is ignored when the module registered first.

`all()` returns the types keyed by post type, in the order the default composition renders them. Reorder the array to
reorder the sections.

## Adding and removing a type

To remove a type, `unset()` it from the array. To add one that the default leaves out (a type that is not public, for
example), build it with `SearchableTypeFactory::forPostType()`, which derives it like the others:

```php
<?php

declare(strict_types=1);

namespace App\Search;

use Modules\MeiliFacets\Contracts\SearchableTypes;
use Modules\MeiliFacets\SiteSearch\SearchableTypeFactory;
use Modules\MeiliFacets\SiteSearch\WooCommerceSearchableTypes;

final readonly class ShopSearchableTypes implements SearchableTypes
{
    public function __construct(
        private WooCommerceSearchableTypes $default,
        private SearchableTypeFactory $factory,
    ) {}

    public function all(): array
    {
        $types = $this->default->all();
        unset($types['page']);

        return [...$types, 'event' => $this->factory->forPostType('event')];
    }
}
```

The type must still be indexed by MeiliScout: a type missing from the index is never searched. `forPostType()` throws
if the post type is not registered in WordPress.

There is no `withBaseFilter()`. To search a type with another base filter, build it with
`SearchableTypeFactory::make()`, which takes the post type, the filter clauses and the fields to search on:

```php
$this->factory->make(
    'event',
    ['post_type = "event"', 'post_status = "publish"', 'NOT facets.event_status = "cancelled"'],
    ['post_title', 'excerpt'],
);
```

The clauses are [Meilisearch filter expressions](https://www.meilisearch.com/docs/learn/filtering_and_sorting/filter_expression_reference),
joined with `AND`. They can only use filterable attributes: `facets.<taxonomy>` is filterable for every taxonomy of an
indexed type (see [What gets indexed](../indexing/README.md)). Keep the `post_type` and `post_status` clauses: without
them the section returns any document of the index.

## The see-all link

The server renders the link to the bare archive. Each time the client paints an answer, it adds the typed term under
the listing's search parameter (`q` by default): « All products » leads to `/shop/?q=creme`. A query string already in
the archive address is kept. When the section is hidden, the link goes back to the bare archive.

The term only does something if the page it leads to renders a MeiliFacets listing, which reads `q`. A blog archive
rendered by WordPress ignores it and lists every post. To lead elsewhere, use `withArchive('https://…')`; to render no
link, use `withoutArchive()`.

If you renamed the listing's search parameter in `query_parameters`, the link uses the new name: see
[Configuration and environment](../reference/configuration.md).

## A card per type

To change the result card for every type, override the view: see [The result card](composition.md#the-result-card).
To change it for one type, write a Blade component, register it, and name it with `withCard()`.

The component prepares its elements from an empty card binding, as the module's own card does. Here, an event card
shows a date that a [card projector](../indexing/README.md#adding-fields-to-the-card) of the project adds to each
event's card, under a field named by the project's own enum, `EventCardField::Date`:

```php
<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Search\EventCardField;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Modules\MeiliFacets\View\CardBinding;
use Modules\MeiliFacets\View\CardFieldElement;
use Modules\MeiliFacets\View\CardHooks;

final class EventSearchCard extends Component
{
    public CardFieldElement $link;

    public CardFieldElement $title;

    public CardFieldElement $date;

    public function __construct()
    {
        $binding = CardBinding::template();
        $hooks = new CardHooks($binding);

        $this->link = $hooks->link();
        $this->title = $hooks->title();
        $this->date = $binding->text(EventCardField::Date);
    }

    public function render(): View
    {
        return view('components.event-search-card');
    }
}
```

```blade
{{-- resources/views/components/event-search-card.blade.php --}}
<a {{ $attributes->class('event-result') }} {{ $link->attributes }}>
    @if ($title->isPresent())
        <span {{ $title->attributes->class('event-result-title') }}>{{ $title }}</span>
    @endif
    @if ($date->isPresent())
        <span {{ $date->attributes->class('event-result-date') }}>{{ $date }}</span>
    @endif
</a>
```

Register the component under an alias in the `boot()` method of a service provider, then pass that alias to
`withCard()`:

```php
use App\View\Components\EventSearchCard;
use Illuminate\Support\Facades\Blade;

public function boot(): void
{
    Blade::component('event-search-card', EventSearchCard::class);
}
```

```php
$types['event'] = $types['event']->withCard('event-search-card');
```

The section renders the card with `<x-dynamic-component>`, which only knows the aliases registered before it is first
used: register in a provider, never from a view.

Rules for a card:

- keep the `url` hook (`$link->attributes`): it is the link Enter follows;
- keep `title`; keep `image`, `summary` and `price` if the card shows them. The client highlights the title and the
  summary;
- text fields (`$binding->text()`) are written as text, never as HTML. The price is the only HTML field;
- the section wraps the card in `<li role="option" data-meili="card">`: the card does not carry the option.

The other ways to bind a card field (attributes, classes, conditions) are in [Overriding views](../customising/views.md).

## Fields searched

`searchOn` becomes the `attributesToSearchOn` of each query. Meilisearch refuses a whole query that names a field the
index does not search, so a type's fields must stay inside the index's search order, set by `SearchableAttributes`:
see [Search relevance](../indexing/relevance.md).

- The default types are narrowed to the search order automatically: if you remove `excerpt` from the order, the types
  stop searching it.
- `withSearchOn()` takes the list as it is. A field outside the order throws when the search renders:

  ```text
  Post type "event" is searched on fields the index does not search: metas.venue. Meilisearch refuses the whole query: add them to SearchableAttributes or remove them from searchOn.
  ```

- A type left with no field to search on throws:

  ```text
  Post type "event" has no field to search: the search order (SearchableAttributes) keeps none of the fields it is searched on. Keep one of them in the order, or remove the type from SearchableTypes.
  ```

Field names are document paths: `post_title`, `excerpt`, `content`, `labels.<taxonomy>`, `metas._sku`. The order of
`searchOn` does not change the ranking: the index ranks by the order of `SearchableAttributes`.

## Watch out

- A type can be declared and still refused: MeiliScout must index it. Its indexed post types setting lives in the
  database, so set it on every environment.
- Renaming a heading with `__()` makes it a string of your project's catalogue; the default headings come from
  WordPress and are already translated.
- Changing a type's `searchOn` takes effect on the next page load. Changing the search order needs a reindex: see
  [Search relevance](../indexing/relevance.md#applying-a-change).

## See also

- [Composing the search panel](composition.md)
- [Search relevance](../indexing/relevance.md)
- [What gets indexed](../indexing/README.md)
- [PHP contracts](../reference/contracts.md)
- [Errors and console messages](../reference/errors.md)
