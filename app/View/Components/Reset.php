<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\ResetShape;
use Modules\MeiliFacets\Listing\CurrentListing;

final class Reset extends ListingComponent
{
    /** Where `php artisan module:publish MeiliFacets` puts the icon the icon shape shows by default. */
    private const string DEFAULT_ICON = 'modules/meilifacets/images/trash.svg';

    public ResetShape $shape;

    public function __construct(
        CurrentListing $listings,
        string $name = '',
        bool $scroll = false,
        ResetShape|string $shape = ResetShape::Text,
    ) {
        parent::__construct($listings, $name, $scroll);

        $this->shape = is_string($shape) ? ResetShape::from($shape) : $shape;
    }

    public function defaultIconUrl(): string
    {
        return asset(self::DEFAULT_ICON);
    }

    public function render(): View
    {
        return view(match ($this->shape) {
            ResetShape::Text, ResetShape::Pill => 'meilifacets::components.reset',
            ResetShape::Icon => 'meilifacets::components.reset-icon',
        });
    }
}
