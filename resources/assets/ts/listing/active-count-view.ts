import { Badge } from '../shared/badge.ts'
import { Entrance } from './entrance.ts'

import type { Contract } from '../shared/contract.ts'
import type { ListingState } from './listing-state.ts'

const FROM_SCALE = 0.9

/**
 * The bare number of values the visitor holds, pending ones included, for the
 * words around it to carry — « Filters n », « Apply (X) ». Hidden and emptied at
 * zero: a counter may describe its button, and a hidden node still describes.
 */
export class ActiveCountView {
    #contract: Contract
    #entry: Entrance

    constructor(contract: Contract) {
        this.#contract = contract
        this.#entry = new Entrance(contract.root.ownerDocument, FROM_SCALE)
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
        const badge = new Badge(counter)
        const appears = badge.appearsWith(count)

        badge.show(count)

        if (appears) {
            this.#entry.play(counter)
        }
    }
}
