import type { Contract } from '../shared/contract.ts'
import type { ListingState } from './listing-state.ts'

/**
 * The bare number of values the visitor holds, pending ones included, for the
 * words around it to carry — « Filter (n) », « Apply (X) ». Hidden at zero.
 */
export class SelectionCountView {
    #contract: Contract

    constructor(contract: Contract) {
        this.#contract = contract
    }

    show(state: ListingState) {
        const count = state.activeFilterCount()

        for (const counter of this.#contract.all('active-count')) {
            if (counter instanceof HTMLElement) {
                counter.textContent = String(count)
                counter.hidden = count === 0
            }
        }
    }
}
