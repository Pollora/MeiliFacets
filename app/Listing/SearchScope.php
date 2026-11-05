<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

/** What a listing filters and reads once a term is searched, typed by the visitor or routed by WordPress. */
final readonly class SearchScope
{
    /**
     * @param  list<string>  $filter  clauses every searched query carries, in place of the base filter
     * @param  list<string>|null  $fields  `attributesToSearchOn`; null searches every searchable attribute
     */
    public function __construct(
        public array $filter,
        public ?array $fields = null,
    ) {}
}
