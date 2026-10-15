<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

/**
 * A listing whose results are drawn by a card of the project's own, on the server and in the template the browser
 * clones.
 */
interface CardComponentListing extends Listing
{
    /**
     * The Blade component, named as written after `<x-`. It receives `binding` (a `View\CardBinding`) and
     * `priority` (an `Enums\ImagePriority`).
     */
    public function cardComponent(): string;
}
