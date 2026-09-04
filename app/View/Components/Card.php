<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\HeadingLevel;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\View\CardDocument;
use Modules\MeiliFacets\View\CardImage;

final class Card extends Component
{
    /** How many cards a theme fits above the fold: layout-dependent, so overridable. */
    public const int DEFAULT_EAGER = 4;

    /** What get_price_html() emits, and nothing more. */
    private const array PRICE_MARKUP = [
        'span' => ['class' => []],
        'bdi' => ['class' => []],
        'del' => ['class' => []],
        'ins' => ['class' => []],
        'small' => ['class' => []],
    ];

    public HeadingLevel $heading;

    public string $title;

    public string $url;

    public CardImage $image;

    /** WooCommerce formats the price at indexing time, markup included. */
    public HtmlString $price;

    /**
     * @param  array<string, mixed>  $card
     */
    public function __construct(
        array $card,
        HeadingLevel|string $heading = HeadingLevel::H3,
        int $rank = 0,
    ) {
        $document = new CardDocument($card);

        // Blade hands attributes over as strings; from() rejects anything else.
        $this->heading = is_string($heading) ? HeadingLevel::from($heading) : $heading;
        $this->title = $document->text(CardField::Title);
        $this->url = $document->text(CardField::Url);
        $this->price = $this->priceOf($document);
        $this->image = CardImage::from($document, $this->title, $this->priority($rank));
    }

    public function render(): View
    {
        return view('meilifacets::components.card');
    }

    /**
     * The index is a store of its own, and get_price_html() runs through a filter
     * any plugin can extend: the markup is narrowed before it reaches the page.
     */
    private function priceOf(CardDocument $card): HtmlString
    {
        $price = $card->text(CardField::Price);

        return new HtmlString($price === '' ? '' : wp_kses($price, self::PRICE_MARKUP));
    }

    private function priority(int $rank): ImagePriority
    {
        return ImagePriority::forRank($rank, (int) config('meilifacets.card.eager', self::DEFAULT_EAGER));
    }
}
