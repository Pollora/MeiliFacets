<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Pollora\Attributes\Action;

final class HiddenStyle
{
    /**
     * `hidden` carries no specificity: a single `.meilifacetsCardPrice { display: block }`
     * in the theme brings every hidden node back. Scoped to the module's own
     * classes so the rest of the page keeps its own rules.
     */
    private const string RULE = '[class^="meilifacets"][hidden],[class*=" meilifacets"][hidden]{display:none!important}';

    #[Action('wp_head')]
    public function print(): void
    {
        echo '<style id="meilifacets-hidden">'.self::RULE.'</style>'."\n";
    }
}
