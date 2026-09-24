import { RESULTS } from '../shared/plan.ts'
import { Range } from '../shared/range.ts'

import type { FilterQuery } from '../shared/filter-query.ts'
import type { ListingState } from '../listing/listing-state.ts'
import type { PriceFields } from '../shared/description.ts'
import type { Answers } from '../shared/search-client.ts'

/** The browser's copy of `Search\PriceQuery`, with `PriceFilter::boundsFrom()`. */
export class PriceQuery implements FilterQuery {
    static readonly KEY = 'bounds'

    #fields: PriceFields

    constructor(fields: PriceFields) {
        this.#fields = fields
    }

    get key() {
        return PriceQuery.KEY
    }

    get fields() {
        return [this.#fields.min, this.#fields.max]
    }

    isMeasuredApart(state: ListingState) {
        return !state.price.isEmpty()
    }

    /**
     * Two intervals overlap unless one ends before the other starts — the same test
     * the server writes, so a filtered page and its first client search agree.
     */
    clause(state: ListingState) {
        const { min, max } = state.price

        return [
            max === null ? '' : `${this.#fields.min} <= ${Range.boundTo(max)}`,
            min === null ? '' : `${this.#fields.max} >= ${Range.boundTo(min)}`,
        ].filter(Boolean).join(' AND ')
    }

    boundsFrom(answers: Answers) {
        const stats = (answers[PriceQuery.KEY] ?? answers[RESULTS])?.facetStats ?? {}

        return this.#widened(stats[this.#fields.min]?.min, stats[this.#fields.max]?.max)
    }

    #widened(min: number | undefined, max: number | undefined) {
        if (min === undefined || max === undefined || Math.floor(min) === Math.ceil(max)) {
            return new Range()
        }

        return new Range(Math.floor(min), Math.ceil(max))
    }
}
