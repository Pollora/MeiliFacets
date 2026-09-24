import { FacetQuery } from './facets/facet-query.ts'
import { PriceQuery } from './price/price-query.ts'
import { SortQuery } from './sort/sort-query.ts'

import type { ListingDescription } from './shared/description.ts'
import type { FilterQuery } from './shared/filter-query.ts'

/** What each filter a listing declares adds to its searches, facets first, as `QueryPlan::filterQueries()`. */
export const filterQueriesOf = (description: ListingDescription): FilterQuery[] => [
    ...description.facets.map((facet) => new FacetQuery(facet)),
    ...(description.priceFields ? [new PriceQuery(description.priceFields)] : []),
    ...(Object.keys(description.sortFilters).length > 0 ? [new SortQuery(description.sortFilters)] : []),
]
