import type { Contract } from '../shared/contract.ts'
import type { FacetsView } from '../facets/facets-view.ts'
import type { ListingState } from '../listing/listing-state.ts'

/**
 * The badge on each toggle: how many values of its facet the visitor holds,
 * pending ones included, like `active-count`. Hidden and emptied at zero.
 */
export class SelectedCountView {
    #contract: Contract
    #facets: FacetsView

    constructor(contract: Contract, facets: FacetsView) {
        this.#contract = contract
        this.#facets = facets
    }

    show(state: ListingState) {
        for (const badge of this.#contract.all('selected-count')) {
            const taxonomy = this.#facets.taxonomyIn(badge)

            if (badge instanceof HTMLElement && taxonomy !== undefined) {
                this.#write(badge, state.selected(taxonomy).length)
            }
        }
    }

    /** The toggle is described by the badge, and a hidden node still describes: zero must say nothing. */
    #write(badge: HTMLElement, count: number) {
        badge.textContent = count === 0 ? '' : String(count)
        badge.hidden = count === 0
    }
}
