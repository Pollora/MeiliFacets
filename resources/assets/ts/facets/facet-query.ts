import { facetField } from '../shared/description.ts'

import type { FilterQuery } from '../shared/filter-query.ts'
import type { ListingState } from '../listing/listing-state.ts'
import type { FacetDescription } from '../shared/description.ts'

const COUNT = 'count:'

// One pass over both characters: escaping them in sequence would let a value
// ending in a backslash close the string.
const ESCAPED = /[\\"]/g

/** The browser's copy of `Search\\FacetQuery`. */
export class FacetQuery implements FilterQuery {
    #facet: FacetDescription

    constructor(facet: FacetDescription) {
        this.#facet = facet
    }

    static keyFor(taxonomy: string) {
        return COUNT + taxonomy
    }

    get key() {
        return FacetQuery.keyFor(this.#facet.taxonomy)
    }

    get fields() {
        return [facetField(this.#facet)]
    }

    isMeasuredApart(state: ListingState) {
        return this.#facet.multiple && state.selected(this.#facet.taxonomy).length > 0
    }

    clause(state: ListingState) {
        const field = facetField(this.#facet)
        const clauses = state.selected(this.#facet.taxonomy).map((value) => `${field} = ${this.#escape(value)}`)

        return clauses.length > 1 ? `(${clauses.join(' OR ')})` : clauses.join('')
    }

    #escape(value: string) {
        return `"${value.replace(ESCAPED, '\\$&')}"`
    }
}
