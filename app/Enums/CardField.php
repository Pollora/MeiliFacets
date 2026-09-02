<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum CardField: string
{
    case Title = 'title';
    case Url = 'url';
    case ImageUrl = 'image_url';
    case ImageAlt = 'image_alt';
    case Price = 'price';
}
