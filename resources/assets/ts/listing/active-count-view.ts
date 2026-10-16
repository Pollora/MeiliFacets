import { Badge } from '../shared/badge.ts'
import { Entrance } from '../shared/entrance.ts'

import type { Contract } from '../shared/contract.ts'
import type { ListingState } from './listing-state.ts'

const ENTRANCE = { from: 'scale(0.9)', duration: '--meili-duration-fade', easing: '--meili-ease' }

/**
 * The bare number of values the visitor holds, pending ones included, for the
 * words around it to carry — « Filters n », « Apply (X) ». Hidden and emptied at
 * zero: a counter may describe its button, and a hidden node still describes.
 */
export class ActiveCountView {
    #contract: Contract
    #entrance: Entrance

    constructor(contract: Contract) {
        this.#contract = contract
        this.#entrance = new Entrance(contract.root.ownerDocument, ENTRANCE)
    }

    show(state: ListingState) {
        const count = state.activeFilterCount()
        const counters = this.#contract.elements('active-count')
        const entering = new Map(counters
            .filter((counter) => new Badge(counter).appearsWith(count))
            .map((counter) => [counter, this.#entrance.timingOf(counter)]))

        counters.forEach((counter) => new Badge(counter).show(count))
        entering.forEach((timing, counter) => this.#entrance.play(counter, timing))
    }
}
