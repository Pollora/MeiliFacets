import { Badge } from '../shared/badge.ts'
import { Contract } from '../shared/contract.ts'

import type { ListingState } from '../listing/listing-state.ts'

/** A control that knows what one filter block holds; `undefined` for a block that is not its own. */
export interface SelectionHolder {
    heldIn(block: Element, state: ListingState): number | undefined
}

/**
 * The badge on each toggle: what its block holds, pending changes included,
 * like `active-count`. Hidden and emptied at zero.
 */
export class ToggleBadgeView {
    #contract: Contract
    #holders: readonly SelectionHolder[]

    constructor(contract: Contract, holders: readonly SelectionHolder[]) {
        this.#contract = contract
        this.#holders = holders
    }

    show(state: ListingState) {
        for (const badge of this.#contract.all('selected-count')) {
            const count = this.#countFor(badge, state)

            if (badge instanceof HTMLElement && count !== undefined) {
                new Badge(badge).show(count)
            }
        }
    }

    #countFor(badge: Element, state: ListingState) {
        const block = badge.closest(Contract.selector('facet'))

        if (block === null) {
            return undefined
        }

        return this.#holders.map((holder) => holder.heldIn(block, state)).find((count) => count !== undefined)
    }
}
