import { ListingState } from './listing-state.js'

/**
 * @import { ListingDescription } from './description.js'
 */

/** Mirrors UrlParameters::UNMAPPED_PREFIX: a bare taxonomy name is a WordPress query var. */
const UNMAPPED_PREFIX = 'f_'

/** Mirrors QueryParameter: `page`, `paged` and `order` are WordPress query vars. */
const RESERVED = { sort: 'sort', query: 'q', page: 'pg' }

export class ListingUrl {
    /** @type {Record<string, string>} */
    #reserved

    /** @type {[string, string][]} */
    #parameters

    /**
     * @param {ListingDescription} description
     */
    constructor(description) {
        this.#reserved = { ...RESERVED, ...description.reserved }
        this.#parameters = description.facets.map(({ taxonomy }) => [
            taxonomy,
            description.params?.[taxonomy] ?? UNMAPPED_PREFIX + taxonomy,
        ])
    }

    /**
     * @param {string} search
     */
    toState(search) {
        const params = new URLSearchParams(search)

        return new ListingState({
            facets: Object.fromEntries(
                this.#parameters
                    .map(([taxonomy, parameter]) => [taxonomy, ListingState.valuesFrom(params.get(parameter) ?? '')])
            ),
            query: params.get(this.#reserved.query) ?? '',
            sort: params.get(this.#reserved.sort),
            page: Number.parseInt(params.get(this.#reserved.page) ?? '1', 10),
        })
    }

    /**
     * Only the query string is rewritten: the path belongs to WordPress.
     *
     * @param {ListingState} state
     */
    toSearch(state) {
        const params = new URLSearchParams()

        for (const [taxonomy, parameter] of this.#parameters) {
            const values = state.selected(taxonomy)

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
