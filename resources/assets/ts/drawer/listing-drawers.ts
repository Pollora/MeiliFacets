import { Drawer } from './drawer.ts'
import { HeldPaint } from './held-paint.ts'

import type { DisclosureGroup } from '../collapsible/disclosure-group.ts'
import type { Contract } from '../shared/contract.ts'

/** The drawers over a listing, and the repaints of the page they hold back while one covers it. */
export class ListingDrawers {
    #drawers: Drawer[]
    #held: HeldPaint

    constructor(contract: Contract, disclosures: DisclosureGroup) {
        this.#held = new HeldPaint(this.#windowOf(contract), () => this.#coverThePage())
        this.#drawers = contract.all('drawer')
            .filter((drawer): drawer is HTMLElement => drawer instanceof HTMLElement)
            .map((drawer) => new Drawer(contract, drawer, {
                hidden: (left) => disclosures.collapseWithin(left),
                uncovered: () => this.#held.release(),
            }))
    }

    start() {
        this.#drawers.forEach((drawer) => drawer.start())

        return this
    }

    paintPage(paint: () => void) {
        this.#held.paint(paint)
    }

    #coverThePage() {
        return this.#drawers.some((drawer) => drawer.coversThePage())
    }

    #windowOf(contract: Contract) {
        const view = contract.root.ownerDocument.defaultView

        if (view === null) {
            throw new Error('[meilifacets] the listing is not in a window.')
        }

        return view
    }
}
