import type { Contract } from '../shared/contract.ts'
import type { ListingState } from './listing-state.ts'

/** The way out of every filter, on every copy the theme placed: shown while the state holds one. */
export class ResetView {
    #contract: Contract

    constructor(contract: Contract) {
        this.#contract = contract
    }

    show(state: ListingState) {
        for (const reset of this.#contract.elements('reset')) {
            reset.hidden = state.isPristine()
        }
    }
}
