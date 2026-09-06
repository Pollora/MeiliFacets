import { BrowserHistory } from './browser-history.js'
import { ListingQuery } from './listing-query.js'
import { ListingUrl } from './listing-url.js'
import { SearchClient } from './search-client.js'

export class Listing {
    #client
    #query
    #url
    #history

    constructor(listing, connection, {
        client = new SearchClient(connection),
        history = new BrowserHistory(),
    } = {}) {
        this.#client = client
        this.#query = new ListingQuery(listing)
        this.#url = new ListingUrl(listing)
        this.#history = history
        this.state = this.#url.toState(this.#history.search())
    }

    // Answers come back keyed like the plan, or null when a fresher search
    // cancelled this one.
    async search() {
        return this.#client.search(this.#query.plan(this.state))
    }

    toggle(taxonomy, value) {
        const values = this.state.facets[taxonomy] ?? []

        this.state.facets[taxonomy] = values.includes(value)
            ? values.filter((current) => current !== value)
            : [...values, value]
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
        this.#history.replace(this.state, this.#url.toSearch(this.state))
    }

    commitPage() {
        this.#history.push(this.state, this.#url.toSearch(this.state))
    }

    // Without this, going back changes the URL and leaves the grid untouched.
    onBack(repaint) {
        this.#history.onPopState(() => {
            this.restoreFrom(this.#history.search())
            repaint()
        })
    }

    restoreFrom(search) {
        this.state = this.#url.toState(search)

        return this
    }
}
