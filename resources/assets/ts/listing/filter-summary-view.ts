import { CountLabel } from '../shared/count-label.ts'

import type { Contract } from '../shared/contract.ts'
import type { ListingDescription } from '../shared/description.ts'
import type { ListingState } from './listing-state.ts'

/** How many filters are on, on every copy the theme placed. */
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
        const label = this.#countLabel.of(this.#description.filterPattern, count)

        for (const badge of this.#contract.elements('active-filters')) {
            badge.textContent = label
            badge.hidden = count === 0
        }
    }
}
