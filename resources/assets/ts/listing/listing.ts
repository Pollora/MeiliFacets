import { ListingQuery } from './listing-query.ts'
import { ListingState } from './listing-state.ts'
import { ListingUrl } from './listing-url.ts'
import { SearchClient, SearchSuperseded } from '../shared/search-client.ts'

import type { BrowserHistory } from './browser-history.ts'
import type { Connection, FacetDescription, ListingDescription, StateDescription } from '../shared/description.ts'
import type { FilterQuery } from '../shared/filter-query.ts'
import type { Answers } from '../shared/search-client.ts'

const IMMEDIATE = 'immediate'

export type HistorySeam = Pick<BrowserHistory, 'record' | 'replace' | 'push' | 'onPopState'>

export type SearchSeam = Pick<SearchClient, 'search'>

export interface ChangeDetail {
    state: ListingState
}

export interface ResultsDetail {
    answers: Answers
    state: ListingState
}

export interface FailedDetail {
    failure: unknown
}

/**
 * What the visitor asked for and what the engine answered: it announces both, the DOM listens.
 *
 * @fires Listing#change    the state moved, nothing has been searched yet
 * @fires Listing#searching a search left while none was under way
 * @fires Listing#results   the engine answered
 * @fires Listing#failed    the engine refused or never answered
 * @fires Listing#settled   no search is under way any more: answered, refused or overtaken
 */
export class Listing extends EventTarget {
    #description: ListingDescription
    #query: ListingQuery
    #url: ListingUrl
    #client: SearchSeam
    #history: HistorySeam
    #state: ListingState
    #searchedFor: string | null = null
    #searchesUnderWay = 0

    /** A page change is a place a visitor can come back to; a filter is not. */
    #keepsHistory = false

    constructor(description: ListingDescription, connection: Connection, {
        filterQueries,
        history,
        client = new SearchClient(connection),
    }: { filterQueries: FilterQuery[], history: HistorySeam, client?: SearchSeam }) {
        super()

        this.#description = description
        this.#query = new ListingQuery(description, filterQueries)
        this.#url = new ListingUrl(description)
        this.#client = client
        this.#history = history
        this.#state = new ListingState(description.state)
    }

    get state() {
        return this.#state
    }

    get searchesAtOnce() {
        return this.#description.apply === IMMEDIATE
    }

    toggle(taxonomy: string, value: string) {
        const facet = this.#facet(taxonomy)

        return facet === undefined ? this : this.#byMode(this.#state.toggling(facet, value))
    }

    sortBy(sort: string | null) {
        return this.#atOnce(this.#state.sortedBy(sort))
    }

    /** A committed range, not a moving handle: dragging previews, releasing filters. */
    priceBetween(min: number | null, max: number | null) {
        return this.#byMode(this.#state.pricedBetween(min, max))
    }

    search(query: string) {
        return this.#byMode(this.#state.searching(query))
    }

    goToPage(page: number) {
        this.#keepsHistory = true

        return this.#atOnce(this.#state.onPage(page))
    }

    withdraw(taxonomy: string, value: string) {
        return this.#atOnce(this.#state.without(taxonomy, value))
    }

    withdrawPrice() {
        return this.#atOnce(this.#state.pricedBetween(null, null))
    }

    reset() {
        return this.#atOnce(this.#state.cleared())
    }

    async apply() {
        this.#writeUrl()
        await this.#search()

        return this
    }

    /** Going back changes the URL; without this the grid would stay behind. */
    listenToHistory() {
        const { name } = this.#description

        this.#history.record(name, this.#state.toDescription())
        this.#history.onPopState(name, (state) => this.#restore(state))

        return this
    }

    #restore(shown: StateDescription) {
        this.#moveTo(new ListingState(shown))

        if (JSON.stringify(this.#state.toDescription()) !== this.#searchedFor) {
            void this.#search()
        }
    }

    /** A search the visitor overtook is not a failure and says nothing. */
    async #search() {
        this.#markSearching()

        try {
            const state = this.#state
            const answers = await this.#client.search(this.#query.plan(state))

            this.#searchedFor = JSON.stringify(state.toDescription())
            this.#announce('results', { answers, state })
        } catch (failure) {
            if (!(failure instanceof SearchSuperseded)) {
                this.#announce('failed', { failure })
            }
        } finally {
            this.#markSettled()
        }
    }

    /** Counted, not flagged: an overtaken search ends while the one that overtook it is still out. */
    #markSearching() {
        this.#searchesUnderWay += 1

        if (this.#searchesUnderWay === 1) {
            this.#announce('searching', null)
        }
    }

    #markSettled() {
        this.#searchesUnderWay -= 1

        if (this.#searchesUnderWay === 0) {
            this.#announce('settled', null)
        }
    }

    #byMode(state: ListingState) {
        this.#moveTo(state)

        if (this.searchesAtOnce) {
            void this.apply()
        }

        return this
    }

    #atOnce(state: ListingState) {
        this.#moveTo(state)

        void this.apply()

        return this
    }

    #moveTo(state: ListingState) {
        this.#state = state
        this.#announce('change', { state })
    }

    #writeUrl() {
        const url = this.#url.toUrl(this.#state)

        const { name } = this.#description

        if (this.#keepsHistory) {
            this.#history.push(name, this.#state.toDescription(), url)
        } else {
            this.#history.replace(name, this.#state.toDescription(), url)
        }

        this.#keepsHistory = false
    }

    #facet(taxonomy: string): FacetDescription | undefined {
        return this.#description.facets.find((facet) => facet.taxonomy === taxonomy)
    }

    #announce(name: string, detail: ChangeDetail | ResultsDetail | FailedDetail | null) {
        this.dispatchEvent(new CustomEvent(name, { detail }))
    }
}
