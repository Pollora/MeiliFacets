import { FilterExpression } from '../shared/filter-expression.ts'

import type { ListingState } from '../listing/listing-state.ts'
import type { SortFilterDescription } from '../shared/description.ts'
import type { FilterQuery } from '../shared/filter-query.ts'

/** The browser's copy of `Search\\SortQuery`. */
export class SortQuery implements FilterQuery {
    static readonly KEY = 'sorted'

    #filters: Readonly<Record<string, SortFilterDescription>>

    constructor(filters: Readonly<Record<string, SortFilterDescription>>) {
        this.#filters = filters
    }

    get key() {
        return SortQuery.KEY
    }

    get fields() {
        return [...new Set(Object.values(this.#filters).map((filter) => filter.field))]
    }

    isMeasuredApart() {
        return false
    }

    clause(state: ListingState) {
        const filter = state.sort !== null && Object.hasOwn(this.#filters, state.sort) ? this.#filters[state.sort] : undefined

        return filter === undefined ? '' : FilterExpression.equals(filter.field, filter.value)
    }

    matchesIn(distribution: Partial<Record<string, Record<string, number>>>): Record<string, number> {
        return Object.fromEntries(Object.entries(this.#filters).map(([sort, { field, value }]) => [sort, distribution[field]?.[value] ?? 0]))
    }
}
