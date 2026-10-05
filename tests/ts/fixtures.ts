import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'

import type { StateChanges } from '../../resources/assets/ts/listing/listing-state.ts'
import type { Connection, ListingDescription, StateDescription } from '../../resources/assets/ts/shared/description.ts'
import type { ListingHistory } from '../../resources/assets/ts/listing/listing.ts'
import type { Answers, SearchQuery, Searcher } from '../../resources/assets/ts/shared/search-client.ts'

/** A listing of products, ten to a page, applied on submit: each test writes only what it is about. */
export const described = (partial: Partial<ListingDescription>) =>
    ({
        name: 'products',
        perPage: 10,
        reachableHits: 1000,
        filter: 'post_type = "product"',
        attributes: ['card'],
        variantResults: null,
        apply: 'submit',
        countPattern: ':count result|:count results',
        filterPattern: ':count active filter|:count active filters',
        totalPattern: ':count item|:count items',
        sortPattern: 'Sort by: :choice',
        reserved: { sort: 'sort', query: 'q', page: 'pg', minPrice: 'min_price', maxPrice: 'max_price' },
        sorts: {},
        locale: 'en',
        pagePath: '/shop',
        pageQuery: '',
        sortFilters: {},
        baseQuery: '',
        minChars: 2,
        delay: 120,
        activeValuePatterns: {
            remove: 'Remove the :label filter',
            query: '“:query”',
            between: ':min – :max',
            from: 'From :min',
            upTo: 'Up to :max',
        },
        searchScope: { filter: partial.filter ?? 'post_type = "product"', fields: null },
        ...partial,
    }) as ListingDescription

export const connection: Connection = { url: 'https://engine.test', key: 'search', index: 'products' }

export const served = (changes: StateChanges = {}) => new ListingState(changes).toDescription()

export class FakeHistory implements ListingHistory {
    pushed: string[] = []
    replaced: string[] = []
    recorded: Partial<Record<string, StateDescription>> = {}
    #restores: [string, (state: StateDescription) => void][] = []

    record(name: string, state: StateDescription) {
        this.recorded = { ...this.recorded, [name]: state }
    }

    replace(name: string, state: StateDescription, url: string) {
        this.replaced.push(url)
        this.record(name, state)
    }

    push(name: string, state: StateDescription, url: string) {
        this.pushed.push(url)
        this.record(name, state)
    }

    onPopState(name: string, restore: (state: StateDescription) => void) {
        this.#restores.push([name, restore])
    }

    goBackTo(name: string, state: StateDescription) {
        this.#restores.filter(([listening]) => listening === name).forEach(([, restore]) => restore(state))
    }
}

export class FakeClient implements Searcher {
    plans: Record<string, SearchQuery>[] = []
    answer: Answers = { results: { hits: [], totalHits: 0 } }
    failure: Error | null = null

    search(queries: Record<string, SearchQuery>) {
        this.plans.push(queries)

        return this.failure === null ? Promise.resolve(this.answer) : Promise.reject(this.failure)
    }
}
