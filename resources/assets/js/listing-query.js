import { facetField } from './description.js'

/**
 * @import { FacetDescription, ListingDescription } from './description.js'
 * @import { ListingState } from './listing-state.js'
 */

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
    /** @type {ListingDescription} */
    #listing

    /**
     * @param {ListingDescription} description
     */
    constructor(description) {
        this.#listing = description
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
        const request = {
            q: state.query,
            filter: this.#filter(state, null),
            facets: this.#fieldsCountedOnMain(state),
            hitsPerPage: perPage,
            page: state.page,
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
            q: state.query,
            filter: this.#filter(state, facet.taxonomy),
            facets: [facetField(facet)],
            hitsPerPage: NO_HIT,
            page: 1,
        }
    }

    #isCountedApart(facet, state) {
        return facet.multiple === true && state.selected(facet.taxonomy).length > 0
    }

    #fieldsCountedOnMain(state) {
        return this.#listing.facets
            .filter((facet) => !this.#isCountedApart(facet, state))
            .map((facet) => facetField(facet))
    }

    // OR within a facet, AND across facets.
    #filter(state, except) {
        const clauses = this.#listing.filter ? [this.#listing.filter] : []

        for (const facet of this.#listing.facets) {
            const values = state.selected(facet.taxonomy)

            if (facet.taxonomy !== except && values.length > 0) {
                clauses.push(this.#facetClause(facet, values))
            }
        }

        return clauses.join(' AND ')
    }

    #facetClause(facet, values) {
        const field = facetField(facet)
        const clauses = values.map((value) => `${field} = ${this.#escape(value)}`)

        return clauses.length > 1 ? `(${clauses.join(' OR ')})` : clauses[0]
    }

    #escape(value) {
        return `"${String(value).replace(ESCAPED, '\\$&')}"`
    }
}
