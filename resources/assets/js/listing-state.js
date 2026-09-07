/**
 * @import { FacetDescription } from './description.js'
 */

const FIRST_PAGE = 1

/** A query string is public input: the bound keeps a crafted URL cheap. */
const MAX_QUERY_LENGTH = 200

const VALUE_SEPARATOR = ','

/**
 * What the visitor has asked for. A value, never edited in place: every gesture
 * hands back a new one, so two states can be compared and a back button can
 * restore one without anybody wondering who wrote what.
 */
export class ListingState {
    /** @type {Readonly<Record<string, readonly string[]>>} */
    #facets

    /** @type {string} */
    #query

    /** @type {string | null} */
    #sort

    /** @type {number} */
    #page

    /**
     * @param {{ facets?: Record<string, string[]>, query?: string, sort?: string | null, page?: number }} [state]
     */
    constructor({ facets = {}, query = '', sort = null, page = FIRST_PAGE } = {}) {
        this.#facets = Object.freeze(Object.fromEntries(
            Object.entries(facets)
                .map(([taxonomy, values]) => [taxonomy, Object.freeze(ListingState.#tidy(values))])
                .filter(([, values]) => values.length > 0)
        ))
        this.#query = query.slice(0, MAX_QUERY_LENGTH)
        this.#sort = sort
        this.#page = Math.max(Math.trunc(page) || FIRST_PAGE, FIRST_PAGE)
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

    /**
     * @param {string} taxonomy
     * @returns {readonly string[]}
     */
    selected(taxonomy) {
        return this.#facets[taxonomy] ?? []
    }

    isPristine() {
        return Object.keys(this.#facets).length === 0
            && this.#query === ''
            && this.#sort === null
            && this.#page === FIRST_PAGE
    }

    /**
     * Ticking or unticking a value. A facet that holds one value at a time
     * replaces what it had; one that holds several adds to it, up to its cap.
     *
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
            ...changes,
        })
    }

    /** Sorted and deduplicated: one state must have one URL, or a cache holds it twice. */
    static #tidy(values) {
        return [...new Set(values.map((value) => value.trim()).filter(Boolean))].sort()
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
