import { ActiveCountView } from './active-count-view.ts'
import { ActiveValuesView } from './active-values-view.ts'
import { FilterSummaryView } from './filter-summary-view.ts'
import { ToggleBadgeView } from '../collapsible/toggle-badge-view.ts'
import { TotalView } from './total-view.ts'

import type { Contract } from '../shared/contract.ts'
import type { ListingDescription } from '../shared/description.ts'
import type { ListingState } from './listing-state.ts'
import type { Listing } from './listing.ts'
import type { SelectionHolder } from '../collapsible/toggle-badge-view.ts'

/** What the summary reads from the rest of the binding: the listing a pill withdraws from, and what each block holds. */
interface SummarySources {
    listing: Listing
    holders: readonly SelectionHolder[]
}

/** The views that sum the listing up — total, active values, filter summary, counts and badges — none of which filters. */
export class SummaryBinding {
    #summary: FilterSummaryView
    #activeCount: ActiveCountView
    #toggleBadges: ToggleBadgeView
    #total: TotalView
    #activeValues: ActiveValuesView

    constructor(contract: Contract, description: ListingDescription, { listing, holders }: SummarySources) {
        this.#summary = new FilterSummaryView(contract, description)
        this.#activeCount = new ActiveCountView(contract)
        this.#toggleBadges = new ToggleBadgeView(contract, holders)
        this.#total = new TotalView(contract, description)
        this.#activeValues = new ActiveValuesView(contract, description, listing)
    }

    start() {
        this.#activeValues.start()

        return this
    }

    /** What the visitor holds, pending changes included: painted as the state moves. */
    showHeld(state: ListingState) {
        this.#summary.show(state)
        this.#activeCount.show(state)
        this.#toggleBadges.show(state)
    }

    /** What the engine answered: painted once the search is back. */
    showAnswered(totalHits: number, state: ListingState) {
        this.#total.show(totalHits)
        this.#activeValues.show(state)
    }
}
