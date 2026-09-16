/**
 * @import { FacetDescription } from './description.js'
 */

export const FIRST_PAGE = 1

/** A query string is public input: the bound keeps a crafted URL cheap. */
const MAX_QUERY_LENGTH = 200

const VALUE_SEPARATOR = ','

/** What the visitor has asked for, never edited in place: every gesture hands back a new value. */
export class ListingState {
    /** @type {Readonly<Record<string, readonly string[]>>} */
    #facets

    /** @type {string} */
    #query

    /** @type {string | null} */
    #sort

    /** @type {number} */
    #page

    #price

    /**
     * @param {{ facets?: Record<string, string[]>, query?: string, sort?: string | null, page?: number,
     *           price?: { min: number | null, max: number | null } }} [state]
     */
    constructor({ facets = {}, query = '', sort = null, page = FIRST_PAGE, price = {} } = {}) {
        this.#facets = Object.freeze(Object.fromEntries(
            Object.entries(facets)
                .map(([taxonomy, values]) => [taxonomy, Object.freeze(ListingState.#tidy(values))])
                .filter(([, values]) => values.length > 0)
        ))
        // Trimmed then cut by code point, like `trim()` + `mb_substr()`: slicing UTF-16
        // units would cut a surrogate pair in half.
        this.#query = [...query.trim()].slice(0, MAX_QUERY_LENGTH).join('')
        this.#sort = sort
        this.#page = Math.max(Math.trunc(page) || FIRST_PAGE, FIRST_PAGE)
        this.#price = Object.freeze({
            min: ListingState.#bound(price.min),
            max: ListingState.#bound(price.max),
        })
    }

    /** A negative price is not a price, and a non-number is not an answer. */
    static #bound(value) {
        const bound = typeof value === 'string' ? Number.parseFloat(value) : value

        return typeof bound === 'number' && Number.isFinite(bound) && bound >= 0 ? bound : null
    }

    get facets() {
        return this.#facets
    }

    get query() {
        return this.#query
    }

    get sort() {
        return this.#sort
    }

    get page() {
        return this.#page
    }

    get price() {
        return this.#price
    }

    /**
     * @param {number | null} min
     * @param {number | null} max
     */
    pricedBetween(min, max) {
        return this.with({ price: { min, max }, page: FIRST_PAGE })
    }

    /**
     * @param {string} taxonomy
     * @returns {readonly string[]}
     */
    selected(taxonomy) {
        return this.#facets[taxonomy] ?? []
    }

    /** Mirrors ListingState on the server: a range counts once, whatever its ends. */
    activeFilterCount() {
        const ticked = Object.values(this.#facets).reduce((total, values) => total + values.length, 0)

        return ticked + (this.#price.min === null && this.#price.max === null ? 0 : 1)
    }

    isPristine() {
        return Object.keys(this.#facets).length === 0
            && this.#query === ''
            && this.#sort === null
            && this.#page === FIRST_PAGE
            && this.#price.min === null
            && this.#price.max === null
    }

    /**
     * @param {FacetDescription} facet
     * @param {string} value
     */
    toggling(facet, value) {
        const held = this.selected(facet.taxonomy)
        const next = held.includes(value)
            ? held.filter((current) => current !== value)
            : facet.multiple ? [...held, value].slice(-facet.cap) : [value]

        return this.with({ facets: { ...this.#facets, [facet.taxonomy]: next }, page: FIRST_PAGE })
    }

    /**
     * @param {string | null} sort
     */
    sortedBy(sort) {
        return this.with({ sort, page: FIRST_PAGE })
    }

    /**
     * @param {string} query
     */
    searching(query) {
        return this.with({ query, page: FIRST_PAGE })
    }

    /**
     * @param {number} page
     */
    onPage(page) {
        return this.with({ page })
    }

    cleared() {
        return new ListingState()
    }

    /**
     * @param {{ facets?: Record<string, string[]>, query?: string, sort?: string | null, page?: number }} changes
     */
    with(changes) {
        return new ListingState({
            facets: /** @type {Record<string, string[]>} */ (structuredClone(this.#facets)),
            query: this.#query,
            sort: this.#sort,
            page: this.#page,
            price: { ...this.#price },
            ...changes,
        })
    }

    /** Sorted and deduplicated: one state must have one URL, or a cache holds it twice. */
    static #tidy(values) {
        return [...new Set(values.map((value) => value.trim()).filter((value) => value.length > 0))].sort()
    }

    /**
     * @param {string} raw
     */
    static valuesFrom(raw) {
        return ListingState.#tidy(raw.split(VALUE_SEPARATOR))
    }

    /**
     * @param {readonly string[]} values
     */
    static valuesTo(values) {
        return [...values].join(VALUE_SEPARATOR)
    }
}
