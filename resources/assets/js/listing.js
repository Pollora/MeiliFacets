import { BrowserHistory } from './browser-history.js'
import { ListingQuery } from './listing-query.js'
import { ListingUrl } from './listing-url.js'
import { SearchClient, SearchSuperseded } from './search-client.js'

/**
 * @import { Connection, FacetDescription, ListingDescription } from './description.js'
 * @import { ListingState } from './listing-state.js'
 */

const IMMEDIATE = 'immediate'

/**
 * What the visitor asked for, and what the engine answered. It holds the state,
 * decides when the URL is written, and announces both — the DOM listens, it is
 * never called into.
 *
 * Two modes are supported because a catalogue decides which one it can afford:
 * `immediate` searches at every gesture, `submit` gathers them until `apply()`.
 *
 * @fires Listing#change   the state moved, nothing has been searched yet
 * @fires Listing#results  the engine answered
 * @fires Listing#failed   the engine refused or never answered
 */
export class Listing extends EventTarget {
    /** @type {ListingDescription} */
    #description

    /** @type {ListingQuery} */
    #query

    /** @type {ListingUrl} */
    #url

    /** @type {SearchClient} */
    #client

    /** @type {BrowserHistory} */
    #history

    /** @type {ListingState} */
    #state

    /** A page change is a place a visitor can come back to; a filter is not. */
    #keepsHistory = false

    /**
     * @param {ListingDescription} description
     * @param {Connection} connection
     * @param {{ client?: SearchClient, history?: BrowserHistory }} [collaborators]
     */
    constructor(description, connection, {
        client = new SearchClient(connection),
        history = new BrowserHistory(),
    } = {}) {
        super()

        this.#description = description
        this.#query = new ListingQuery(description)
        this.#url = new ListingUrl(description)
        this.#client = client
        this.#history = history
        this.#state = this.#url.toState(this.#history.search())
    }

    get state() {
        return this.#state
    }

    get searchesAtOnce() {
        return this.#description.apply === IMMEDIATE
    }

    /**
     * @param {string} taxonomy
     * @param {string} value
     */
    toggle(taxonomy, value) {
        const facet = this.#facet(taxonomy)

        return facet === undefined ? this : this.#moveTo(this.#state.toggling(facet, value))
    }

    /**
     * @param {string | null} sort
     */
    sortBy(sort) {
        return this.#moveTo(this.#state.sortedBy(sort))
    }

    /**
     * @param {string} query
     */
    search(query) {
        return this.#moveTo(this.#state.searching(query))
    }

    /**
     * @param {number} page
     */
    goToPage(page) {
        this.#keepsHistory = true

        return this.#moveTo(this.#state.onPage(page))
    }

    reset() {
        return this.#moveTo(this.#state.cleared())
    }

    /**
     * Writes the URL, asks the engine, and announces the answer. A search the
     * visitor overtook is not a failure and says nothing.
     */
    async apply() {
        this.#writeUrl()

        try {
            const answers = await this.#client.search(this.#query.plan(this.#state))

            this.#announce('results', { answers, state: this.#state })
        } catch (failure) {
            if (!(failure instanceof SearchSuperseded)) {
                this.#announce('failed', { failure })
            }
        }

        return this
    }

    /** Going back changes the URL; without this the grid would stay behind. */
    listenToHistory() {
        this.#history.onPopState(() => {
            this.#state = this.#url.toState(this.#history.search())
            this.#announce('change', { state: this.#state })
            void this.apply()
        })

        return this
    }

    /**
     * @param {ListingState} state
     */
    #moveTo(state) {
        this.#state = state
        this.#announce('change', { state })

        if (this.searchesAtOnce) {
            void this.apply()
        }

        return this
    }

    #writeUrl() {
        const search = this.#url.toSearch(this.#state)

        this.#keepsHistory
            ? this.#history.push(this.#state.facets, search)
            : this.#history.replace(this.#state.facets, search)

        this.#keepsHistory = false
    }

    /**
     * @param {string} taxonomy
     * @returns {FacetDescription | undefined}
     */
    #facet(taxonomy) {
        return this.#description.facets.find((facet) => facet.taxonomy === taxonomy)
    }

    /**
     * @param {string} name
     * @param {object} detail
     */
    #announce(name, detail) {
        this.dispatchEvent(new CustomEvent(name, { detail }))
    }
}
