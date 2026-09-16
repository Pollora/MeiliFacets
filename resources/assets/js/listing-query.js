import { facetField } from './description.js'
import { FIRST_PAGE } from './listing-state.js'

/**
 * @import { FacetDescription, ListingDescription } from './description.js'
 * @import { ListingState } from './listing-state.js'
 */

// One pass over both characters: escaping them in sequence would let a value
// ending in a backslash close the string.
const ESCAPED = /[\\"]/g
const RESULTS = 'results'
const COUNT = 'count:'
const BOUNDS = 'bounds'
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

    static get BOUNDS() {
        return BOUNDS
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

        if (this.#measuresPriceApart(state)) {
            queries[BOUNDS] = this.#priceBounds(state)
        }

        return queries
    }

    #results(state) {
        const { perPage, attributes, sorts } = this.#listing
        const request = {
            q: state.query,
            filter: this.#filter(state, null),
            facets: [
                ...this.#fieldsCountedOnMain(state),
                ...this.#measuresPriceApart(state) ? [] : this.#priceFieldList(),
            ],
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
            page: FIRST_PAGE,
        }
    }

    /**
     * @param {ListingState} state
     */
    #priceBounds(state) {
        return {
            q: state.query,
            filter: this.#joined([...this.#baseClauses(), ...this.#facetClauses(state, null)]),
            facets: this.#priceFieldList(),
            hitsPerPage: NO_HIT,
            page: FIRST_PAGE,
        }
    }

    /**
     * @param {ListingState} state
     */
    #measuresPriceApart(state) {
        return (state.price.min !== null || state.price.max !== null) && this.#priceFieldList().length > 0
    }

    #priceFieldList() {
        const fields = this.#listing.priceFields

        return fields ? [fields.min, fields.max] : []
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
        return this.#joined([...this.#baseClauses(), ...this.#facetClauses(state, except), this.#priceClause(state)])
    }

    /**
     * @param {string[]} clauses
     */
    #joined(clauses) {
        return clauses.filter((clause) => clause !== '').join(' AND ')
    }

    #baseClauses() {
        return this.#listing.filter ? [this.#listing.filter] : []
    }

    #facetClauses(state, except) {
        return this.#listing.facets.flatMap((facet) => {
            const values = state.selected(facet.taxonomy)

            return facet.taxonomy !== except && values.length > 0 ? [this.#facetClause(facet, values)] : []
        })
    }

    /**
     * Two intervals overlap unless one ends before the other starts — the same test
     * the server writes, so a filtered page and its first client search agree.
     *
     * @param {ListingState} state
     */
    #priceClause(state) {
        const fields = this.#listing.priceFields

        if (!fields) {
            return ''
        }

        return [
            state.price.max === null ? '' : `${fields.min} <= ${state.price.max}`,
            state.price.min === null ? '' : `${fields.max} >= ${state.price.min}`,
        ].filter(Boolean).join(' AND ')
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
