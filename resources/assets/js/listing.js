import { ListingQuery } from './listing-query.js'
import { ListingUrl } from './listing-url.js'
import { SearchClient } from './search-client.js'

export class Listing {
    #client
    #query
    #url

    constructor(listing, connection) {
        this.#client = new SearchClient(connection)
        this.#query = new ListingQuery(listing)
        this.#url = new ListingUrl(listing)
        this.state = this.#url.toState(window.location.search)
    }

    async search() {
        return this.#client.search(this.#query.build(this.state))
    }

    toggle(taxonomy, value) {
        const values = this.state.facets[taxonomy] ?? []
        const kept = values.filter((current) => current !== value)

        this.state.facets[taxonomy] = kept.length === values.length ? [...values, value] : kept
        this.state.page = 1

        return this
    }

    sortBy(sort) {
        this.state.sort = sort
        this.state.page = 1

        return this
    }

    goToPage(page) {
        this.state.page = page

        return this
    }

    reset() {
        this.state = { facets: {}, query: '', sort: null, page: 1 }

        return this
    }

    commitUrl() {
        window.history.pushState(this.state, '', window.location.pathname + this.#url.toSearch(this.state))
    }

    restoreFrom(search) {
        this.state = this.#url.toState(search)

        return this
    }
}
