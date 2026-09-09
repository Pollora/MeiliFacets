<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;

final class Facets extends ListingComponent
{
    public function render(): View|string
    {
        if ($this->listing->remainingFacets() === [] && ! $this->listing->applyMode()->needsButton()) {
            return '';
        }

        return view('meilifacets::components.facets', [
            'applyMode' => $this->listing->applyMode()->value,
            'needsApplyButton' => $this->listing->applyMode()->needsButton(),
        ]);
    }
}
