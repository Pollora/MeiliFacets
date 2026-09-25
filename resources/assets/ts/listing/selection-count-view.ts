import { CountEntry } from './count-entry.ts'

import type { Contract } from '../shared/contract.ts'
import type { ListingState } from './listing-state.ts'

/**
 * The bare number of values the visitor holds, pending ones included, for the
 * words around it to carry — « Filters n », « Apply (X) ». Hidden and emptied at
 * zero: a counter may describe its button, and a hidden node still describes.
 */
export class SelectionCountView {
    #contract: Contract
    #entry: CountEntry

    constructor(contract: Contract) {
        this.#contract = contract
        this.#entry = new CountEntry(contract.root.ownerDocument)
    }

    show(state: ListingState) {
        const count = state.activeFilterCount()

        for (const counter of this.#contract.all('active-count')) {
            if (counter instanceof HTMLElement) {
                this.#write(counter, count)
            }
        }
    }

    #write(counter: HTMLElement, count: number) {
        const appears = counter.hidden && count > 0

        counter.textContent = count === 0 ? '' : String(count)
        counter.hidden = count === 0

        if (appears) {
            this.#entry.play(counter)
        }
    }
}
