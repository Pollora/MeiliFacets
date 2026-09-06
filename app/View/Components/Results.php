<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\ImagePriority;
use Modules\MeiliFacets\Http\RobotsPolicy;
use Modules\MeiliFacets\Seo\ItemList;

final class Results extends ListingComponent
{
    /** How many cards a theme fits above the fold: layout-dependent, so overridable. */
    public const int DEFAULT_EAGER = 4;

    private ?int $eager = null;

    public function priority(int $rank): ImagePriority
    {
        $this->eager ??= $this->eagerCards();

        return ImagePriority::forRank($rank, $this->eager);
    }

    /**
     * Nothing structured on a page search engines are told to skip — and the
     * list would go stale as soon as the client filters.
     */
    public function itemList(): ?ItemList
    {
        if (app(RobotsPolicy::class)->appliesTo(request()->query())) {
            return null;
        }

        $resolved = $this->listing();

        return new ItemList($resolved->cards(), $resolved->pagination()->offset());
    }

    public function render(): View
    {
        return view('meilifacets::components.results');
    }

    private function eagerCards(): int
    {
        return (int) config('meilifacets.card.eager', self::DEFAULT_EAGER);
    }
}
