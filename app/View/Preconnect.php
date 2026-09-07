<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View;

use Modules\MeiliFacets\Http\ListingPage;
use Modules\MeiliFacets\Search\BrowserConnection;
use Pollora\Attributes\Action;

/**
 * The engine answers from another origin than the page, so the browser has a
 * DNS lookup, a handshake and a TLS negotiation to pay before its first filter —
 * measured at 65 ms on a machine talking to itself. Opening the connection while
 * the page loads spends that time where nobody is waiting.
 */
final readonly class Preconnect
{
    public function __construct(
        private BrowserConnection $connection,
        private ListingPage $page,
    ) {}

    #[Action('wp_head', priority: 2)]
    public function warmTheEngineOrigin(): void
    {
        $origin = $this->connection->isConfigured() ? $this->connection->origin() : '';

        if ($origin === '' || ! $this->page->isCurrent()) {
            return;
        }

        printf('<link rel="preconnect" href="%s" crossorigin>'."\n", esc_url($origin));
    }
}
