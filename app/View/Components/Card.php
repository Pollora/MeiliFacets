<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\HeadingLevel;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\View\CardDocument;
use Modules\MeiliFacets\View\CardImage;

final class Card extends ContractComponent
{
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
        ImagePriority $priority = ImagePriority::Lazy,
    ) {
        $document = new CardDocument($card);

        // Blade hands attributes over as strings; from() rejects anything else.
        $this->heading = is_string($heading) ? HeadingLevel::from($heading) : $heading;
        $this->title = $document->text(CardField::Title);
        $this->url = $document->text(CardField::Url);
        $this->price = new HtmlString($document->text(CardField::Price));
        $this->image = CardImage::from($document, $this->title, $priority);
    }

    public function render(): View
    {
        return view('meilifacets::components.card');
    }
}
