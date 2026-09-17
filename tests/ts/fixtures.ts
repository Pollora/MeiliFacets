import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'

import type { StateChanges } from '../../resources/assets/ts/listing/listing-state.ts'
import type { Connection, ListingDescription, StateDescription } from '../../resources/assets/ts/shared/description.ts'
import type { HistorySeam, SearchSeam } from '../../resources/assets/ts/listing/listing.ts'
import type { Answers, SearchQuery } from '../../resources/assets/ts/shared/search-client.ts'

export const described = (partial: Partial<ListingDescription>) => ({ locale: 'en', pagePath: '/shop', pageQuery: '', ...partial }) as ListingDescription

export const connection: Connection = { url: 'https://engine.test', key: 'search', index: 'products' }

export const served = (changes: StateChanges = {}) => new ListingState(changes).toDescription()

export class FakeHistory implements HistorySeam {
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

export class FakeClient implements SearchSeam {
    plans: Record<string, SearchQuery>[] = []
    answer: Answers = { results: { hits: [], totalHits: 0 } }
    failure: Error | null = null

    search(queries: Record<string, SearchQuery>) {
        this.plans.push(queries)

        return this.failure === null ? Promise.resolve(this.answer) : Promise.reject(this.failure)
    }
}
