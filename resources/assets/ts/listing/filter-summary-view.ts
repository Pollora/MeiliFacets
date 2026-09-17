import { CountLabel } from '../shared/count-label.ts'

import type { Contract } from '../shared/contract.ts'
import type { ListingDescription } from '../shared/description.ts'
import type { ListingState } from './listing-state.ts'

/** How many filters are on, and the way out of them. */
export class FilterSummaryView {
    #contract: Contract
    #description: ListingDescription
    #countLabel: CountLabel

    constructor(contract: Contract, description: ListingDescription) {
        this.#contract = contract
        this.#description = description
        this.#countLabel = new CountLabel(description.locale)
    }

    show(state: ListingState) {
        const count = state.activeFilterCount()
        const badge = this.#contract.one('active-filters')
        const reset = this.#contract.one('reset')

        if (badge instanceof HTMLElement) {
            badge.textContent = this.#countLabel.of(this.#description.filterPattern, count)
            badge.hidden = count === 0
        }

        if (reset instanceof HTMLElement) {
            reset.hidden = state.isPristine()
        }
    }
}
