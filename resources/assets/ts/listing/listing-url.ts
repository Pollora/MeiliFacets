import { Range } from '../shared/range.ts'
import { FIRST_PAGE, ListingState } from './listing-state.ts'

import type { FacetDescription, ListingDescription } from '../shared/description.ts'

/** `PageAddress::PAGE_PLACEHOLDER` */
const PAGE_PLACEHOLDER = ':page'

export class ListingUrl {
    #reserved: ListingDescription['reserved']
    #parameters: [FacetDescription, string][]
    #pagePath: string
    #pagePathPattern: string
    #pageQuery: string

    constructor(description: ListingDescription) {
        this.#reserved = description.reserved
        this.#parameters = description.facets.flatMap((facet) => {
            const parameter = description.params[facet.taxonomy]

            return parameter === undefined ? [] : [[facet, parameter]]
        })
        this.#pagePath = description.pagePath
        this.#pagePathPattern = description.pagePathPattern
        this.#pageQuery = description.pageQuery
    }

    toUrl(state: ListingState, page = state.page) {
        return this.#pathOf(page) + this.toSearch(state)
    }

    linksFor(state: ListingState) {
        const search = this.toSearch(state)

        return (page: number) => this.#pathOf(page) + search
    }

    /** The page a link of this listing points to, or `null` for any other address. */
    pageAt(pathname: string) {
        const path = ListingUrl.#untrailed(pathname)

        if (path === ListingUrl.#untrailed(this.#pagePath)) {
            return FIRST_PAGE
        }

        const [before = '', after = ''] = ListingUrl.#untrailed(this.#pagePathPattern).split(PAGE_PLACEHOLDER)
        const page = Number(path.slice(before.length, path.length - after.length))
        const isPagePath = path.startsWith(before) && path.endsWith(after)

        return isPagePath && Number.isInteger(page) && page > FIRST_PAGE ? page : null
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
    }

    static #untrailed(path: string) {
        return path.length > 1 ? path.replace(/\/+$/, '') : path
    }

    #pathOf(page: number) {
        return page > FIRST_PAGE ? this.#pagePathPattern.replace(PAGE_PLACEHOLDER, String(page)) : this.#pagePath
    }

    #writeBounds(params: URLSearchParams, state: ListingState) {
        const bounds = [[state.price.min, this.#reserved.minPrice], [state.price.max, this.#reserved.maxPrice]] as const

        for (const [bound, parameter] of bounds) {
            if (bound !== null) {
                params.set(parameter, Range.formatBound(bound))
            }
        }
    }
}
