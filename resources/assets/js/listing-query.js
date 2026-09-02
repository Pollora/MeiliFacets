import { FACET_PREFIX } from './facet-field.js'

// One pass over both characters: escaping them in sequence would let a value
// ending in a backslash close the string.
const ESCAPED = /[\\"]/g

export class ListingQuery {
    #listing

    constructor(listing) {
        this.#listing = listing
    }

    build(state) {
        const { perPage, facets, attributes, sorts } = this.#listing
        const request = {
            q: state.query ?? '',
            filter: this.#filter(state.facets ?? {}),
            facets,
            limit: perPage,
            offset: (Math.max(state.page ?? 1, 1) - 1) * perPage,
        }

        if (attributes) {
            request.attributesToRetrieve = attributes
        }

        if (sorts?.[state.sort]) {
            request.sort = sorts[state.sort]
        }

        return request
    }

    // OR within a facet, AND across facets.
    #filter(selected) {
        const clauses = this.#listing.filter ? [this.#listing.filter] : []

        for (const [taxonomy, values] of Object.entries(selected)) {
            if (values.length > 0) {
                clauses.push(this.#facetClause(taxonomy, values))
            }
        }

        return clauses.join(' AND ')
    }

    #facetClause(taxonomy, values) {
        const field = FACET_PREFIX + taxonomy
        const clauses = values.map((value) => `${field} = ${this.#escape(value)}`)

        return clauses.length > 1 ? `(${clauses.join(' OR ')})` : clauses[0]
    }

    #escape(value) {
        return `"${String(value).replace(ESCAPED, '\\$&')}"`
    }
}
