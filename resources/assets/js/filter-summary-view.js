import { countLabel } from './facet-counts.js'

/**
 * @import { Contract } from './contract.js'
 * @import { ListingDescription } from './description.js'
 * @import { ListingState } from './listing-state.js'
 */

/** How many filters are on, and the way out of them. */
export class FilterSummaryView {
    /** @type {Contract} */
    #contract

    /** @type {ListingDescription} */
    #description

    /**
     * @param {Contract} contract
     * @param {ListingDescription} description
     */
    constructor(contract, description) {
        this.#contract = contract
        this.#description = description
    }

    /**
     * @param {ListingState} state
     */
    show(state) {
        const count = state.activeFilterCount()
        const badge = this.#contract.one('active-filters')
        const reset = this.#contract.one('reset')

        if (badge instanceof HTMLElement) {
            badge.textContent = countLabel(this.#description.filterPattern, count)
            badge.hidden = count === 0
        }

        if (reset instanceof HTMLElement) {
            reset.hidden = state.isPristine()
        }
    }
}
