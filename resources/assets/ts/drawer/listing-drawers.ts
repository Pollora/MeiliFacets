import { Drawer } from './drawer.ts'
import { DeferredRepaint } from './deferred-repaint.ts'

import type { DisclosureGroup } from '../collapsible/disclosure-group.ts'
import type { Contract } from '../shared/contract.ts'

/** The drawers over a listing, and the repaints of the page they hold back while one covers it. */
export class ListingDrawers {
    #drawers: Drawer[]
    #deferredRepaint: DeferredRepaint

    constructor(contract: Contract, disclosures: DisclosureGroup) {
        this.#deferredRepaint = new DeferredRepaint(contract.window, () => this.#coverThePage())
        this.#drawers = contract.all('drawer')
            .filter((drawer): drawer is HTMLElement => drawer instanceof HTMLElement)
            .map((drawer) => new Drawer(contract, drawer, {
                hidden: (left) => disclosures.collapseWithin(left),
                uncovered: () => this.#deferredRepaint.release(),
            }))
    }

    start() {
        this.#drawers.forEach((drawer) => drawer.start())

        return this
    }

    repaintBehindDrawer(paint: () => void) {
        this.#deferredRepaint.paint(paint)
    }

    #coverThePage() {
        return this.#drawers.some((drawer) => drawer.coversThePage())
    }
}
