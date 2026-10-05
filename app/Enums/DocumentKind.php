<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

/** What a product's document stands for once its variants have documents of their own. */
enum DocumentKind: string
{
    /** A product whose variants have documents of their own. */
    case Parent = 'parent';

    /** A variant of a product, under the product's fields. */
    case Variant = 'variant';
}
