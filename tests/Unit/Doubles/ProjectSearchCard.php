<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Illuminate\View\Component;

/** A card a project hands one type through `withCard()`. */
final class ProjectSearchCard extends Component
{
    public const string ALIAS = 'probe-search-card';

    public function render(): string
    {
        return '<a class="probeSearchCard" href="" data-meili="url"><span data-meili="title"></span></a>';
    }
}
