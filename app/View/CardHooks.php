<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Enums\Hook;
use Modules\MeiliFacets\Enums\ImagePriority;

final readonly class CardHooks
{
    private const string LOADING = 'loading';

    private const string FETCH_PRIORITY = 'fetchpriority';

    public function __construct(private CardBinding $binding) {}

    public function link(): CardFieldElement
    {
        return CardFieldElement::of($this->binding->attributes(['href' => CardField::Url]))->with($this->binding->hook(Hook::Url));
    }

    public function image(ImagePriority $priority): CardFieldElement
    {
        return $this->binding->onlyWith(CardField::ImageUrl)->with(
            $this->binding->attributes([
                'src' => CardField::ImageUrl,
                'srcset' => CardField::ImageSrcset,
                'sizes' => CardField::ImageSizes,
                'alt' => [CardField::ImageAlt, CardField::Title],
                'width' => CardField::ImageWidth,
                'height' => CardField::ImageHeight,
            ]),
            [self::LOADING => $priority->loading(), self::FETCH_PRIORITY => $priority->fetchPriority()],
            $this->binding->hook(Hook::Image),
        );
    }

    public function thumbnail(): CardFieldElement
    {
        return $this->binding->onlyWith(CardField::ImageUrl)->with(
            $this->binding->attributes([
                'src' => CardField::ImageUrl,
                'width' => CardField::ImageWidth,
                'height' => CardField::ImageHeight,
            ]),
            $this->binding->hook(Hook::Image),
        );
    }

    public function title(): CardFieldElement
    {
        return $this->binding->text(CardField::Title)->with($this->binding->hook(Hook::Title));
    }

    public function summary(): CardFieldElement
    {
        return $this->binding->text(CardField::Summary)->with($this->binding->hook(Hook::Summary));
    }

    public function price(): CardFieldElement
    {
        return $this->binding->price()->with($this->binding->hook(Hook::Price));
    }
}
