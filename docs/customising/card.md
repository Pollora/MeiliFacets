# Your own card

This page covers how a theme renders its own result card, with its own markup and fields, and lets the module fill it
on the server and in the browser.

You want to…

- [see a complete card, from the indexed fields to the markup](#a-complete-example);
- [know how a card is filled in the browser](#how-a-card-is-filled);
- [write the binding attributes](#the-binding-attributes);
- [prepare the elements in PHP](#preparing-the-elements-in-php);
- [add your own fields to the card](#adding-your-own-fields);
- [show the variant the filters point to](#card-variants);
- [know what the markup contract asks of a card](#what-the-contract-asks-of-a-card);
- [know the rules that apply to every field](#rules);
- [give the site search its own card](#a-card-for-the-site-search).

## A complete example

The theme card below shows a brand line above the title, a volume under it, the price, and an add-to-cart link. Three
files do the work: an enum that names the fields, a projector that writes them into the index, and a Blade component
that renders them.

**1. Name the fields.** A field name is a contract between indexing and rendering, so it lives in an enum:

```php
<?php

declare(strict_types=1);

namespace App\Shop;

enum ShopCardField: string
{
    case Brand = 'brand';
    case Volume = 'volume';
    case CartUrl = 'cart_url';
}
```

**2. Project them.** The projector decorates the module's own, so the title, link, image and price stay:

```php
<?php

declare(strict_types=1);

namespace App\Shop;

use Modules\MeiliFacets\Contracts\CardProjector;
use WC_Product;
use WP_Post;

final readonly class ShopCardProjector implements CardProjector
{
    public function __construct(private CardProjector $card) {}

    /**
     * @return array<string, mixed>
     */
    public function project(WP_Post $post): array
    {
        return [
            ...$this->card->project($post),
            ...$this->shopFields($post),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function shopFields(WP_Post $post): array
    {
        $product = wc_get_product($post);

        if (! $product instanceof WC_Product) {
            return [];
        }

        $brands = wp_get_post_terms($post->ID, 'product_brand', ['fields' => 'names']);

        return array_filter([
            ShopCardField::Brand->value => is_array($brands) ? implode(', ', $brands) : '',
            ShopCardField::Volume->value => $product->get_attribute('pa_volume'),
            ShopCardField::CartUrl->value => $product->add_to_cart_url(),
        ], static fn (string $value): bool => $value !== '');
    }
}
```

Stack it on the module's projector with `extend()`, in the `register()` method of one of your service providers:

```php
use App\Shop\ShopCardProjector;
use Modules\MeiliFacets\Contracts\CardProjector;

$this->app->extend(
    CardProjector::class,
    fn (CardProjector $card): CardProjector => new ShopCardProjector($card),
);
```

Then reindex: `wp meiliscout index`. Until then, the documents carry the old card and the new fields are absent.

**3. Render them.** The component class prepares one element per field; the view prints them with plain Blade. The
class goes in `<theme>/app/View/Components/ShopCard.php`, here for a theme named Acme:

```php
<?php

declare(strict_types=1);

namespace Theme\Acme\View\Components;

use App\Shop\ShopCardField;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\View\CardBinding;
use Modules\MeiliFacets\View\CardFieldElement;
use Modules\MeiliFacets\View\CardHooks;

final class ShopCard extends Component
{
    public CardFieldElement $link;

    public CardFieldElement $image;

    public CardFieldElement $brand;

    public CardFieldElement $title;

    public CardFieldElement $volume;

    public CardFieldElement $price;

    public CardFieldElement $addToCart;

    public function __construct(CardBinding $binding, ImagePriority $priority = ImagePriority::Lazy)
    {
        $hooks = new CardHooks($binding);

        $this->link = $hooks->link();
        $this->image = $hooks->image($priority);
        $this->title = $hooks->title();
        $this->price = $hooks->price();
        $this->brand = $binding->text(ShopCardField::Brand);
        $this->volume = $binding->text(ShopCardField::Volume);
        $this->addToCart = $binding->onlyWith(ShopCardField::CartUrl)->with(
            $binding->attributes(['href' => ShopCardField::CartUrl, 'data-product_id' => CardField::Id]),
        );
    }

    public function render(): View
    {
        return view('components.shop-card');
    }
}
```

```blade
{{-- <theme>/resources/views/components/shop-card.blade.php --}}
<article {{ $attributes->class('shopCard') }}>
    <a {{ $link->attributes->class('shopCardLink') }}>
        @if ($image->isPresent())
            <img {{ $image->attributes->class('shopCardImage') }} decoding="async">
        @endif
        @if ($brand->isPresent())
            <p {{ $brand->attributes->class('shopCardBrand') }}>{{ $brand }}</p>
        @endif
        @if ($title->isPresent())
            <h3 {{ $title->attributes->class('shopCardTitle') }}>{{ $title }}</h3>
        @endif
    </a>
    @if ($volume->isPresent())
        <p {{ $volume->attributes->class('shopCardVolume') }}>{{ $volume }}</p>
    @endif
    @if ($price->isPresent())
        <p {{ $price->attributes->class('shopCardPrice') }}>{{ $price }}</p>
    @endif
    @if ($addToCart->isPresent())
        <a {{ $addToCart->attributes->class('shopCardCart') }}>{{ __('Add to cart') }}</a>
    @endif
</article>
```

There is nothing to register. A Pollora theme's `ThemeComponentProvider` maps the `theme` component namespace to
`Theme\<Name>\View\Components`, so the class above is `<x-theme::shop-card>`. A component that lives elsewhere, such
as in `app/`, is registered like any Blade component: `Blade::component('shop-card', ShopCard::class)` in a provider's
`boot()`.

**4. Use it in the listing.** Override the module's card view, and hand your component what the module's card
component received:

```blade
{{-- <theme>/resources/views/modules/meilifacets/components/listing/card.blade.php --}}
<x-theme::shop-card :binding="$binding" :priority="$priority" />
```

This one view serves both the cards rendered by the server and the `<template>` the browser clones: the same
component renders both, with a different `CardBinding`. A card shared with other pages, such as a home page carousel,
takes a `CardBinding::of($card)` built from whatever array that page holds.

## How a card is filled

A listing renders its cards twice over:

1. **On the server**, each card of the first page is rendered with its data (`CardBinding::of($card)`). An element
   with nothing to show is left out by its `@if`.
2. **In a `<template>`**, the same view is rendered once with no data (`CardBinding::template()`, through
   `<x-meilifacets::listing.card-template />`). Every element is there, empty, and carries attributes that name the
   field it shows.

After each search, the browser clones the template's first element for every result, writes each field where the
attributes say, removes what has nothing to show, writes the price, then inserts the card. Because every binding is
written before the card enters the page, a script that initialises on insertion (an Alpine `x-data`, for example)
reads the final values.

For the add-to-cart link above, the template holds:

```html
<a data-meili-attr="href:cart_url data-product_id:id" data-meili-if="cart_url" class="shopCardCart">Add to cart</a>
```

and a server-rendered card holds the values only. The browser never binds a card the server rendered: it draws every
card it shows from a copy of the template.

```html
<a href="/shop/?add-to-cart=12" data-product_id="12" class="shopCardCart">Add to cart</a>
```

## The binding attributes

Five attributes, read by the browser on every card it draws. You do not write them by hand: `CardBinding` writes them
(see [Preparing the elements in PHP](#preparing-the-elements-in-php)). They are listed here so you can read the
markup.

| Attribute | Effect | Example |
| --- | --- | --- |
| `data-meili-text="field"` | writes the field as text; the element is removed when the text is empty | `data-meili-text="brand"` |
| `data-meili-attr="name:field …"` | writes one attribute per pair; pairs are separated by spaces | `data-meili-attr="href:cart_url data-product_id:id"` |
| `data-meili-attr="name:a\|b"` | the attribute takes the first of the fields that holds a value | `data-meili-attr="alt:image_alt\|title"` |
| `data-meili-class="class:field …"` | adds the class when the field is true, removes it otherwise | `data-meili-class="is-new:fresh"` |
| `data-meili-class-list="field"` | adds the classes the field holds, space-separated, to the element's own | `data-meili-class-list="cart_class"` |
| `data-meili-if="field"` | the element is removed when the field is empty or false | `data-meili-if="cart_url"` |
| `data-meili-if="!field"` | the element is removed when the field is true | `data-meili-if="!image_url"` |

- A field name is made of letters, digits and underscores.
- In a pair, the field is what follows the **last** colon, so a class that holds a colon works:
  `md:hidden:fresh` toggles `md:hidden`.
- `data-meili-class` takes one field per class, without fallback.
- `data-meili-class-list` only adds: it never removes a class an earlier drawing added. A listing draws every card on a
  fresh copy of the template; the search panel redraws a card it keeps for the same result, whose field holds the same
  classes. It carries classes a platform computed for you — WooCommerce's `add_to_cart_button ajax_add_to_cart`, which
  its script reads — so the card does not have to rebuild the rule that sets them.
- An element takes one `data-meili-class-list`. `->with()` refuses a second one; a `merge()` of your own keeps the
  first silently, so the browser would draw only its classes. Bind the second field on a child element.
- `data-meili-if` takes a single condition. There is no `and` or `or`: nest two elements, or project a field that
  holds the combined answer.

## Preparing the elements in PHP

Each element is a `CardFieldElement`, prepared by the component class from a `CardBinding`. The view only prints it.

| Call | Returns |
| --- | --- |
| `CardBinding::of(array $card)` | a binding over one card's fields |
| `CardBinding::template()` | a binding with no data, for the `<template>` |
| `$binding->text($field)` | the field as escaped text, with `data-meili-text`; absent when empty |
| `$binding->price()` | the price as HTML; absent when empty |
| `$binding->onlyWith($field)` | an element present when the field is true, with `data-meili-if` |
| `$binding->onlyWithout($field)` | an element present when the field is false, with `data-meili-if="!…"` |
| `$binding->attributes(['href' => $field, 'alt' => [$a, $b]])` | a `ComponentAttributeBag` holding the values and `data-meili-attr` |
| `$binding->classes(['is-new md:hidden' => $field])` | a `ComponentAttributeBag` holding `class` and `data-meili-class` |
| `$binding->classList($field)` | a `ComponentAttributeBag` holding the field's classes and `data-meili-class-list` |
| `$element->with($bag, [...])` | the element with more attributes, merged as Laravel's `merge()` merges them |
| `$element->containing($text)` | the element holding a fixed text, escaped |

The binding attributes are written by `CardBinding::template()` only; a rendered card holds the values.

A field is named by an enum case or a string. `CardField` names the module's fields: `Id`, `Title`, `Url`,
`ImageUrl`, `ImageSrcset`, `ImageSizes`, `ImageAlt`, `ImageWidth`, `ImageHeight`, `Price`, `Summary`, `Variants`,
`SeveralVariants` and `OutOfStock` (see [Card variants](#card-variants)).

`CardHooks` prepares the module's own elements and adds the `data-meili` hook each one carries:

| Method | Element |
| --- | --- |
| `link()` | `href` from `url`, hook `url` |
| `image(ImagePriority $priority)` | `src`, `srcset`, `sizes`, `alt` (falling back to the title), `width`, `height`, `loading` and `fetchpriority`; present only with an image; hook `image` |
| `thumbnail()` | `src`, `width`, `height`; present only with an image; hook `image` |
| `title()` | the title as text, hook `title` |
| `summary()` | the summary as text, hook `summary` |
| `price()` | the price as HTML, hook `price` |

In the view, the pattern is always the same:

```blade
@if ($brand->isPresent())
    <p {{ $brand->attributes->class('shopCardBrand') }}>{{ $brand }}</p>
@endif
```

- `isPresent()` is false when the card has nothing to show for that element. In the template it is always true.
- `$element->attributes` is a `ComponentAttributeBag`: `->class()` adds the view's classes, as on any component.
- `{{ $element }}` prints the content, already escaped. Blade does not escape it twice.

Two labels picked by one field, such as « In stock » and « Sold out », are two elements: one from `onlyWith()`, one
from `onlyWithout()`, each given its text by `containing(__('…'))`. No translated text goes through the index.

## Adding your own fields

1. Declare an enum of field names, as `ShopCardField` above. A literal string works too, but an enum keeps the name
   checked on both sides.
2. Decorate `CardProjector` and stack your projector with `$this->app->extend()`. A `bind()` would replace the whole
   stack, and the WooCommerce price with it.
3. Return only the fields that hold something. A field the card does not need is left out rather than written empty.
4. Reindex.

The projector runs at indexing time. Anything it writes is stored in the document and read back as is: a value that
depends on the visitor (a cart, a login, a currency) does not belong in the card.

## Card variants

The product is the unit of a listing: it never appears twice. When it is sold in several ways, its card shows the
product as projected — WooCommerce's price range, every volume — until a filter concerns its variants; then it shows
the one the visitor filtered on — the 400 ml bottle and its price when `400ml` is checked.

**1. The module reads them.** With WooCommerce active, every variable product carries its variants in
`card.variants`, read when the product is indexed from the variations WooCommerce offers
(`get_available_variations()`, which applies its visibility rules and « Hide out of stock items »). Each variant
holds:

- the terms it carries, keyed by taxonomy. A custom attribute typed on the product is not a taxonomy, and no facet
  filters on it: it is left out. A variation sold for « any » value carries no term for that attribute;
- its displayed price, from the same list as `price.min` and `price.max` (`get_variation_prices()`), so on the same
  scale;
- whether it is in stock (`in_stock`), and whether it is on sale (`on_sale`);
- the card fields it shows instead of the product's: `price` (its price HTML), `url` (the product page with that
  variation selected) and, when the variation has an image of its own, every image field — empty where that image
  has none, so no field of the product's image stays under it.

A `variants` list a `CardProjector` returns is always dropped: only the module's, read from WooCommerce, is indexed.
Without WooCommerce, no card has variants. Override every other link that leads to the product page, a « choose »
button's for instance, through your own fields below.

**Your own fields.** Bind `Contracts\VariantFields`: it receives each `WC_Product_Variation` and returns fields added
to the module's, which they override. An empty string or array is left out. `id`, `variants`, `several_variants` and
`out_of_stock` are set by the module and dropped from a variant's fields.

```php
use App\Shop\ShopCardField;
use Modules\MeiliFacets\Contracts\VariantFields;
use WC_Product_Variation;

final readonly class ShopVariantFields implements VariantFields
{
    public function project(WC_Product_Variation $variation): array
    {
        return [ShopCardField::Volume->value => $variation->get_attribute('pa_volume')];
    }
}

// In a service provider of the project:
$this->app->bind(VariantFields::class, ShopVariantFields::class);
```

**2. Nothing to do in the listing.** The server and the browser apply the same rule to every card before binding it:

- variants are applied only when a filter **concerns** them: a facet on a variation attribute — the same rule that
  makes the grid read the variant documents. A price range alone does not: the grid ranks the product by its own
  range, as WooCommerce does. A listing of your own applies variants only if it implements
  `Contracts\VariantScopedListing`. Otherwise the card is shown exactly as projected;
- a variant **matches** when, for every active facet whose taxonomy it carries, one of its terms is selected, and its
  price lies within the asked range when there is one. A facet it does not carry rules nothing out;
- under a sort that keeps the products on sale, only a variant **on sale** matches, as only its document answers the
  grid's filter;
- among the matching variants, those **in stock** are offered; only when none is in stock are the ones out of stock
  offered;
- among the offered variants, the **cheapest** wins; its fields are merged over the card's. The first listed wins a
  tie. When none matches, the card is shown as projected;
- when **several** variants are offered, the card gets `several_variants` set to `true`; when the shown variant is
  out of stock, it gets `out_of_stock` set to `true`. Both are reset whenever a variant is shown, whatever the
  projected card held;
- the `variants` list itself never reaches the binding.

**3. Say « from » in the view.** Bind a prefix on the flag, and give its text in Blade:

```php
$this->from = $binding->onlyWith(CardField::SeveralVariants);
```

```blade
@if ($from->isPresent())
    <span {{ $from->attributes->class('shopCardFrom') }}>{{ __('Starting at') }}</span>
@endif
```

**4. A card outside the listing** has no filter: bind it as projected, with nothing to call. Only the listing reads
the variants.

**5. Show what is out of stock.** With WooCommerce, the module sets `out_of_stock` on the card of every product
WooCommerce holds out of stock, whatever its type — a variable product when none of its variations is in stock.
When the listing shows a variant, the flag follows that variant instead. Bind its rendering on the flag, as for
« from », with `$binding->onlyWith(CardField::OutOfStock)`. The module lists products out of stock even when « Hide
out of stock items » is checked; only their variants out of stock are left out.

**A price range alone.** The engine lists a product whose price range overlaps the one asked for, as WooCommerce
does: a product sold at 26 and 39 is listed for 30 to 35, while none of its variants is priced within that range. Its
card shows its whole range; a variant is shown once a variation attribute is checked.

## What the contract asks of a card

The client checks the markup before it starts (see [Overriding views](views.md)). For cards, it asks for very
little:

- **in a listing card, nothing.** A card without an image, a title or a price starts;
- **in a site search card, only `url`**: the link that Enter follows.

The other hooks are read when they are present:

| Hook | Read by |
| --- | --- |
| `price` | the listing and the search, which write the price there as HTML |
| `url` | the search, which takes the result links out of the tab order and follows the active one on Enter |
| `title`, `summary` | the search, which highlights the matched words there |
| `image` | the stylesheets only |

A field without a hook is filled by its binding attributes alone.

## Rules

**An absent field reads as empty.** Text is empty, an attribute is removed, a class is removed, a condition is
false. A node never keeps the template's value nor another card's. An index built before a field was added gives a
card without that field, never a broken one.

**An element with nothing to show is not in the DOM.** The server leaves it out (`isPresent()` is false); the
browser removes it after binding a clone. This covers an empty `data-meili-text`, a false `data-meili-if`, and an
empty price. An element whose only binding is an attribute stays, without that attribute.

**Values are written the same way on both sides.** A string as is; a finite number as JavaScript writes it (`12`,
`1e+21`); nothing for anything else. True means `true`, a non-zero number, a non-empty string (`"0"` included), or a
non-empty list or object. An attribute value is written without the whitespace around it.

**Only some attributes can be bound.** `href`, `src`, `srcset`, `sizes`, `alt`, `title`, `width`, `height`,
`value`, `datetime`, and any `aria-*` or `data-*` attribute, except `data-meili*`. Nothing else: no `on*`, no
`style`, no `id`, no `class` (use `classes()`). An attribute name starts with a lowercase letter and holds lowercase
letters, digits, `_` and `-`. On the server, a refused attribute or a malformed field name throws
`Modules\MeiliFacets\View\BindingRefused` when the view renders. In the browser, a refused binding written by hand is
ignored.

**URLs must be http(s) or relative.** `href`, `src` and every URL in a `srcset` accept an `http:` or `https:` URL or a
relative one. Any other scheme (`javascript:`, `data:`, `mailto:`…) removes the attribute. The URL is read the way a
browser reads it: tabs and line breaks inside are dropped, spaces around are ignored, the scheme is case-insensitive.
`width` and `height` accept a positive whole number only. An empty value removes the attribute rather than writing
`href=""`, which would link the current page.

**`data-*` is free, and its risk is the theme's.** Some libraries run what they find in a `data-*` attribute. A
card that binds a field into such an attribute would have that field's value run. The module forbids nothing more:
the theme chooses what it binds, and the index is written by your projector, not by visitors. Bind a `data-*`
attribute only to a field your projector writes itself, and never under a name that a library loaded on the page
interprets.

**The price is the only field written as HTML.** WooCommerce formats it with markup at indexing time. Every other
field is written as text: `e()` on the server, `textContent` in the browser. The summary is stored decoded, so a `<`
in an excerpt is shown, never parsed.

**The document `ID` is added to the bound card as `id`.** The module adds it to every card it binds, on the server
and in the browser, from the document's primary key. It replaces an `id` your projector would write, so a projector
never stores it. A card rendered outside a listing, from an array your page builds, adds `id` itself. `id` is a
field name, not the `id` attribute: bind it into a `data-*` attribute, as `data-product_id` above.

**Everything in a card can be read by any visitor.** The browser searches with the public search key, which can read
the `card` field of every document. Never project a field you would not print on the page.

## A card for the site search

The site search renders its cards only in the browser: its card view is always rendered with
`CardBinding::template()`.

- **For every type**: override `<theme>/resources/views/modules/meilifacets/components/search/card.blade.php`. It
  receives `$link`, `$image` (from `thumbnail()`), `$title`, `$summary` and `$price`.
- **For one type**: decorate `SearchableTypes` and call `withCard('theme::shop-search-card')` on that type, with the
  name of a Blade component as you would write it after `<x-` (see [Searchable content types](../search/types.md)).
  The class below, in `<theme>/app/View/Components/ShopSearchCard.php`, is found through the theme's component
  namespace; a component that lives elsewhere must be registered before the search renders.

```php
<?php

declare(strict_types=1);

namespace Theme\Acme\View\Components;

use App\Shop\ShopCardField;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Modules\MeiliFacets\View\CardBinding;
use Modules\MeiliFacets\View\CardFieldElement;
use Modules\MeiliFacets\View\CardHooks;

final class ShopSearchCard extends Component
{
    public CardFieldElement $link;

    public CardFieldElement $image;

    public CardFieldElement $brand;

    public CardFieldElement $title;

    public CardFieldElement $price;

    public function __construct()
    {
        $binding = CardBinding::template();
        $hooks = new CardHooks($binding);

        $this->link = $hooks->link();
        $this->image = $hooks->thumbnail();
        $this->title = $hooks->title();
        $this->price = $hooks->price();
        $this->brand = $binding->text(ShopCardField::Brand);
    }

    public function render(): View
    {
        return view('components.shop-search-card');
    }
}
```

```blade
{{-- <theme>/resources/views/components/shop-search-card.blade.php --}}
<a {{ $link->attributes->class('shopSearchCard') }}>
    @if ($image->isPresent())
        <img {{ $image->attributes->class('shopSearchCardImage') }} alt="" loading="lazy" decoding="async">
    @endif
    @if ($brand->isPresent())
        <span {{ $brand->attributes->class('shopSearchCardBrand') }}>{{ $brand }}</span>
    @endif
    @if ($title->isPresent())
        <span {{ $title->attributes->class('shopSearchCardTitle') }}>{{ $title }}</span>
    @endif
    @if ($price->isPresent())
        <span {{ $price->attributes->class('shopSearchCardPrice') }}>{{ $price }}</span>
    @endif
</a>
```

The section wraps each card in `<li role="option" data-meili="card">`: your card does not carry the option itself.
The thumbnail is decorative (`alt=""`), since the link already reads the title.

## Watch out

- **Reindex after changing the projector.** A field is in the documents only once they are indexed again.
- **One root element per card template.** The browser clones the first element of the `<template>` only. In the
  module's results view, that element is the `<li>`; a results override that puts two elements there loses the
  second without an error.
- **Do not bind a field into an Alpine directive or any expression.** A directive is not a bindable attribute. Bind
  the value into a `data-*` attribute and read it from the expression, which must cope with a missing value:

  ```blade
  <div x-data="favorite($el.dataset.productId)" {{ $binding->attributes(['data-product-id' => CardField::Id]) }}>
  ```

- **The image is frozen at indexing time.** Changing an image's alt text or regenerating thumbnails does not reindex
  the products that use it. Reindex after `wp media regenerate`.
- **`card.image_size` has no effect on your projector** if you replace the module's instead of decorating it. Choose
  the size in your projector — and set `card.image_size` to the same size: a variant's own image is always read at
  `card.image_size`, and the card would change its format once a size is checked.

## See also

- [Overriding views](views.md)
- [PHP extension points](php.md)
- [Composing the search panel](../search/composition.md)
- [Searchable content types](../search/types.md)
- [What gets indexed](../indexing/README.md)
- [`data-meili` hooks](../reference/hooks.md)
- [PHP contracts](../reference/contracts.md)
