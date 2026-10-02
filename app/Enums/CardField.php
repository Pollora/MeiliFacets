<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum CardField: string
{
    case Id = 'id';
    case Title = 'title';
    case Url = 'url';
    case ImageUrl = 'image_url';
    case ImageSrcset = 'image_srcset';
    case ImageSizes = 'image_sizes';
    case ImageAlt = 'image_alt';
    case ImageWidth = 'image_width';
    case ImageHeight = 'image_height';
    case Price = 'price';
    case Summary = 'summary';
    case Variants = 'variants';
    case SeveralVariants = 'several_variants';
}
