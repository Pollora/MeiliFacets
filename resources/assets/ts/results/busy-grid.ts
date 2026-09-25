import type { Listing } from '../listing/listing.ts'
import type { Contract } from '../shared/contract.ts'

const BUSY = 'aria-busy'

/**
 * `aria-busy` on the grid from the moment a search leaves until none is out, answered, refused or overtaken.
 * The dimming is the stylesheet's, after a delay a quick answer never reaches: nothing to cancel here.
 */
export class BusyGrid {
    #contract: Contract

    constructor(contract: Contract) {
        this.#contract = contract
    }

    watch(listing: Pick<Listing, 'addEventListener'>) {
        listing.addEventListener('searching', () => this.#grid()?.setAttribute(BUSY, 'true'))
        listing.addEventListener('settled', () => this.#grid()?.removeAttribute(BUSY))

        return this
    }

    #grid() {
        return this.#contract.one('results')
    }
}
