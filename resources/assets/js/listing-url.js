import { ListingState } from './listing-state.js'

/**
 * @import { FacetDescription, ListingDescription } from './description.js'
 */

export class ListingUrl {
    /** @type {Record<string, string>} */
    #reserved

    /** @type {[FacetDescription, string][]} */
    #parameters

    /** @type {string[]} */
    #sorts

    /**
     * @param {ListingDescription} description
     */
    constructor(description) {
        this.#reserved = description.reserved
        this.#parameters = description.facets.map((facet) => [facet, description.params[facet.taxonomy]])
        this.#sorts = Object.keys(description.sorts)
    }

    /**
     * @param {string} search
     */
    toState(search) {
        const params = new URLSearchParams(search)

        return new ListingState({
            facets: Object.fromEntries(
                this.#parameters.map(([facet, parameter]) => [facet.taxonomy, this.#valuesOf(params, facet, parameter)])
            ),
            query: params.get(this.#reserved.query) ?? '',
            sort: this.#declaredSort(params.get(this.#reserved.sort)),
            page: Number.parseInt(params.get(this.#reserved.page) ?? '1', 10),
        })
    }

    /**
     * Mirrors StateReader::values: a crafted URL must not turn into thousands of clauses.
     *
     * @param {URLSearchParams} params
     * @param {FacetDescription} facet
     * @param {string} parameter
     */
    #valuesOf(params, facet, parameter) {
        return ListingState.valuesFrom(params.get(parameter) ?? '').slice(0, facet.multiple ? facet.cap : 1)
    }

    /**
     * Mirrors StateReader: a sort the listing does not declare is no sort at all.
     *
     * @param {string | null} sort
     */
    #declaredSort(sort) {
        return sort !== null && this.#sorts.includes(sort) ? sort : null
    }

    /**
     * Only the query string is rewritten: the path belongs to WordPress.
     *
     * @param {ListingState} state
     */
    toSearch(state) {
        const params = new URLSearchParams()

        for (const [facet, parameter] of this.#parameters) {
            const values = state.selected(facet.taxonomy)

            if (values.length > 0) {
                params.set(parameter, ListingState.valuesTo(values))
            }
        }

        if (state.query) {
            params.set(this.#reserved.query, state.query)
        }

        if (state.sort) {
            params.set(this.#reserved.sort, state.sort)
        }

        if (state.page > 1) {
            params.set(this.#reserved.page, String(state.page))
        }

        // Commas are legal in a query string: keeping them unescaped keeps the URL readable.
        const search = params.toString().replace(/%2C/g, ',')

        return search ? `?${search}` : ''
    }
}
