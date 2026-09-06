import { FACET_PREFIX } from './facet-prefix.js'

// One pass over both characters: escaping them in sequence would let a value
// ending in a backslash close the string.
const ESCAPED = /[\\"]/g
const RESULTS = 'results'
const COUNT = 'count:'
const NO_HIT = 0

/**
 * Mirrors QueryPlan on the server: the same state must produce the same searches
 * on both sides, or the grid contradicts itself between render and first click.
 */
export class ListingQuery {
    #listing

    constructor(listing) {
        this.#listing = listing
    }

    static get RESULTS() {
        return RESULTS
    }

    static countKey(taxonomy) {
        return COUNT + taxonomy
    }

    // Results plus one search per constrained multi-select facet, keyed apart.
    plan(state) {
        const queries = { [RESULTS]: this.#results(state) }

        for (const facet of this.#listing.facets) {
            if (this.#isCountedApart(facet, state)) {
                queries[ListingQuery.countKey(facet.taxonomy)] = this.#counting(facet, state)
            }
        }

        return queries
    }

    #results(state) {
        const { perPage, attributes, sorts } = this.#listing
        const page = Math.max(state.page ?? 1, 1)
        const request = {
            q: state.query ?? '',
            filter: this.#filter(state, null),
            facets: this.#fieldsCountedOnMain(state),
            hitsPerPage: perPage,
            page,
        }

        if (attributes) {
            request.attributesToRetrieve = attributes
        }

        if (sorts?.[state.sort]) {
            request.sort = sorts[state.sort]
        }

        return request
    }

    // Counts a facet as if its own constraint were lifted, so its other values
    // stay reachable.
    #counting(facet, state) {
        return {
            q: state.query ?? '',
            filter: this.#filter(state, facet.taxonomy),
            facets: [this.#field(facet)],
            hitsPerPage: NO_HIT,
            page: 1,
        }
    }

    #isCountedApart(facet, state) {
        return facet.multiple === true && (state.facets?.[facet.taxonomy]?.length ?? 0) > 0
    }

    #fieldsCountedOnMain(state) {
        return this.#listing.facets
            .filter((facet) => !this.#isCountedApart(facet, state))
            .map((facet) => this.#field(facet))
    }

    // OR within a facet, AND across facets.
    #filter(state, except) {
        const clauses = this.#listing.filter ? [this.#listing.filter] : []

        for (const facet of this.#listing.facets) {
            const values = state.facets?.[facet.taxonomy] ?? []

            if (facet.taxonomy !== except && values.length > 0) {
                clauses.push(this.#facetClause(facet, values))
            }
        }

        return clauses.join(' AND ')
    }

    #facetClause(facet, values) {
        const field = this.#field(facet)
        const clauses = values.map((value) => `${field} = ${this.#escape(value)}`)

        return clauses.length > 1 ? `(${clauses.join(' OR ')})` : clauses[0]
    }

    #field(facet) {
        return FACET_PREFIX + facet.taxonomy
    }

    #escape(value) {
        return `"${String(value).replace(ESCAPED, '\\$&')}"`
    }
}
