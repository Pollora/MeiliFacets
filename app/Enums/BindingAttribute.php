<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum BindingAttribute: string
{
    case Text = 'data-meili-text';
    case Attribute = 'data-meili-attr';
    case ClassName = 'data-meili-class';
    case ClassList = 'data-meili-class-list';
    case Condition = 'data-meili-if';

    public const string PAIR_SEPARATOR = ':';

    public const string LIST_SEPARATOR = ' ';

    public const string FALLBACK_SEPARATOR = '|';

    public const string NEGATION = '!';
}
