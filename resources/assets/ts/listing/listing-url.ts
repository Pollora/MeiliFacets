import { Range } from '../shared/range.ts'
import { ListingState } from './listing-state.ts'

import type { FacetDescription, ListingDescription } from '../shared/description.ts'

export class ListingUrl {
    #reserved: ListingDescription['reserved']
    #parameters: [FacetDescription, string][]
    #pagePath: string
    #pageQuery: string

    constructor(description: ListingDescription) {
        this.#reserved = description.reserved
        this.#parameters = description.facets.flatMap((facet) => {
            const parameter = description.params[facet.taxonomy]

            return parameter === undefined ? [] : [[facet, parameter]]
        })
        this.#pagePath = description.pagePath
        this.#pageQuery = description.pageQuery
    }

    toUrl(state: ListingState) {
        return this.#pagePath + this.toSearch(state)
    }

    toSearch(state: ListingState) {
        const params = new URLSearchParams()

        this.#writeFacets(params, state)
        this.#writeReserved(params, state)
        this.#writeBounds(params, state)

        // Commas are legal in a query string: keeping them unescaped keeps the URL readable.
        const own = params.toString().replace(/%2C/g, ',')
        const search = [own, this.#pageQuery].filter((part) => part !== '').join('&')

        return search ? `?${search}` : ''
    }

    #writeFacets(params: URLSearchParams, state: ListingState) {
        for (const [facet, parameter] of this.#parameters) {
            const values = state.selected(facet.taxonomy)

            if (values.length > 0) {
                params.set(parameter, ListingState.valuesTo(values))
            }
        }
    }

    #writeReserved(params: URLSearchParams, state: ListingState) {
        if (state.query) {
            params.set(this.#reserved.query, state.query)
        }

        if (state.sort) {
            params.set(this.#reserved.sort, state.sort)
        }

        if (state.page > 1) {
            params.set(this.#reserved.page, String(state.page))
        }
    }

    #writeBounds(params: URLSearchParams, state: ListingState) {
        const bounds = [[state.price.min, this.#reserved.minPrice], [state.price.max, this.#reserved.maxPrice]] as const

        for (const [bound, parameter] of bounds) {
            if (bound !== null) {
                params.set(parameter, Range.boundTo(bound))
            }
        }
    }
}
