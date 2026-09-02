import { FACET_PREFIX } from './facet-field.js'

const VALUE_SEPARATOR = ','
const SORT_PARAM = 'sort'
const PAGE_PARAM = 'page'
const QUERY_PARAM = 'q'

export class ListingUrl {
    #parameters

    // The taxonomy/parameter mapping never changes: resolved once instead of on
    // every URL read and write.
    constructor(listing) {
        this.#parameters = listing.facets.map((field) => {
            const taxonomy = field.slice(FACET_PREFIX.length)

            return [taxonomy, listing.params?.[taxonomy] ?? taxonomy]
        })
    }

    toState(search) {
        const params = new URLSearchParams(search)
        const facets = {}

        for (const [taxonomy, parameter] of this.#parameters) {
            const raw = params.get(parameter)

            if (raw) {
                facets[taxonomy] = raw.split(VALUE_SEPARATOR).filter(Boolean)
            }
        }

        return {
            facets,
            query: params.get(QUERY_PARAM) ?? '',
            sort: params.get(SORT_PARAM) ?? null,
            page: Number.parseInt(params.get(PAGE_PARAM) ?? '1', 10) || 1,
        }
    }

    // Only the query string is rewritten: the path is WooCommerce's and stays untouched.
    toSearch(state) {
        const params = new URLSearchParams()

        for (const [taxonomy, parameter] of this.#parameters) {
            const values = state.facets?.[taxonomy]

            if (values?.length > 0) {
                params.set(parameter, values.join(VALUE_SEPARATOR))
            }
        }

        if (state.query) {
            params.set(QUERY_PARAM, state.query)
        }

        if (state.sort) {
            params.set(SORT_PARAM, state.sort)
        }

        if (state.page > 1) {
            params.set(PAGE_PARAM, String(state.page))
        }

        // Commas are legal in a query string: keeping them unescaped keeps the URL readable.
        const search = params.toString().replace(/%2C/g, ',')

        return search ? `?${search}` : ''
    }
}
