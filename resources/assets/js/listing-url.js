const VALUE_SEPARATOR = ','
// Mirrors UrlParameters::UNMAPPED_PREFIX: a bare taxonomy name is a WordPress query var.
const UNMAPPED_PREFIX = 'f_'
// Mirror QueryParameter: `page`, `paged` and `order` are WordPress query vars.
const RESERVED = { sort: 'sort', query: 'q', page: 'pg' }

export class ListingUrl {
    #parameters
    #reserved

    // The mapping never changes, and both reads and writes walk it.
    constructor(listing) {
        this.#reserved = { ...RESERVED, ...listing.reserved }
        this.#parameters = listing.facets.map(({ taxonomy }) => [
            taxonomy,
            listing.params?.[taxonomy] ?? UNMAPPED_PREFIX + taxonomy,
        ])
    }

    toState(search) {
        const params = new URLSearchParams(search)
        const facets = {}

        for (const [taxonomy, parameter] of this.#parameters) {
            const values = this.#values(params, parameter)

            if (values.length > 0) {
                facets[taxonomy] = values
            }
        }

        return {
            facets,
            query: params.get(this.#reserved.query) ?? '',
            sort: params.get(this.#reserved.sort) ?? null,
            page: Number.parseInt(params.get(this.#reserved.page) ?? '1', 10) || 1,
        }
    }

    // Sorted on both sides: one state must have one URL, or Varnish caches it twice.
    #values(params, parameter) {
        const raw = params.get(parameter) ?? ''

        return raw.split(VALUE_SEPARATOR).map((value) => value.trim()).filter(Boolean).sort()
    }

    // Only the query string is rewritten: the path belongs to WordPress.
    toSearch(state) {
        const params = new URLSearchParams()

        for (const [taxonomy, parameter] of this.#parameters) {
            const values = state.facets?.[taxonomy]

            if (values?.length > 0) {
                params.set(parameter, [...values].sort().join(VALUE_SEPARATOR))
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
