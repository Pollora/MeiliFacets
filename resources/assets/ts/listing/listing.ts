import { ListingQuery } from './listing-query.ts'
import { ListingState } from './listing-state.ts'
import { ListingUrl } from './listing-url.ts'
import { SearchClient, SearchSuperseded } from '../shared/search-client.ts'
import { PendingSearches } from '../shared/pending-searches.ts'

import type { BrowserHistory } from './browser-history.ts'
import type { Connection, FacetDescription, ListingDescription, StateDescription } from '../shared/description.ts'
import type { FilterQuery } from '../shared/filter-query.ts'
import type { Answers, Searcher } from '../shared/search-client.ts'

const IMMEDIATE = 'immediate'

export type ListingHistory = Pick<BrowserHistory, 'record' | 'replace' | 'push' | 'onPopState'>

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
    #client: Searcher
    #history: ListingHistory
    #state: ListingState
    #searchedFor: string | null = null
    #pendingSearches = new PendingSearches({
        searching: () => this.#dispatch('searching', null),
        settled: () => this.#dispatch('settled', null),
    })

    /** A page change is a place a visitor can come back to; a filter is not. */
    #keepsHistory = false

    constructor(description: ListingDescription, connection: Connection, {
        filterQueries,
        history,
        client = new SearchClient(connection),
    }: { filterQueries: FilterQuery[], history: ListingHistory, client?: Searcher }) {
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

    get appliesImmediately() {
        return this.#description.apply === IMMEDIATE
    }

    toggle(taxonomy: string, value: string) {
        const facet = this.#facet(taxonomy)

        return facet === undefined ? this : this.#applyIfImmediate(this.#state.toggling(facet, value))
    }

    sortBy(sort: string | null) {
        return this.#applyNow(this.#state.sortedBy(sort))
    }

    /** A committed range, not a moving handle: dragging previews, releasing filters. */
    priceBetween(min: number | null, max: number | null) {
        return this.#applyIfImmediate(this.#state.pricedBetween(min, max))
    }

    search(query: string) {
        return this.#applyIfImmediate(this.#state.searching(query))
    }

    goToPage(page: number) {
        this.#keepsHistory = true

        return this.#applyNow(this.#state.onPage(page))
    }

    remove(taxonomy: string, value: string) {
        return this.#applyNow(this.#state.without(taxonomy, value))
    }

    removePrice() {
        return this.#applyNow(this.#state.pricedBetween(null, null))
    }

    reset() {
        return this.#applyNow(this.#state.cleared())
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
        this.#setState(new ListingState(shown))

        if (JSON.stringify(this.#state.toDescription()) !== this.#searchedFor) {
            void this.#search()
        }
    }

    /** A search the visitor overtook is not a failure and says nothing. */
    async #search() {
        this.#pendingSearches.start()

        try {
            const state = this.#state
            const answers = await this.#client.search(this.#query.plan(state))

            this.#searchedFor = JSON.stringify(state.toDescription())
            this.#dispatch('results', { answers, state })
        } catch (failure) {
            if (!(failure instanceof SearchSuperseded)) {
                this.#dispatch('failed', { failure })
            }
        } finally {
            this.#pendingSearches.finish()
        }
    }

    #applyIfImmediate(state: ListingState) {
        this.#setState(state)

        if (this.appliesImmediately) {
            void this.apply()
        }

        return this
    }

    #applyNow(state: ListingState) {
        this.#setState(state)

        void this.apply()

        return this
    }

    #setState(state: ListingState) {
        this.#state = state
        this.#dispatch('change', { state })
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

    #dispatch(name: string, detail: ChangeDetail | ResultsDetail | FailedDetail | null) {
        this.dispatchEvent(new CustomEvent(name, { detail }))
    }
}
