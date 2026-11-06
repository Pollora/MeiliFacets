import { Drawer } from './drawer.ts'
import { DeferredRepaint } from './deferred-repaint.ts'
import { ShowMoreView } from './show-more-view.ts'

import type { DisclosureGroup } from '../collapsible/disclosure-group.ts'
import type { Contract } from '../shared/contract.ts'

/** The drawers over a listing, and the repaints of the page they hold back while one covers it. */
export class ListingDrawers {
    #drawers: Drawer[]
    #showMoreViews: ShowMoreView[]
    #deferredRepaint: DeferredRepaint

    constructor(contract: Contract, disclosures: DisclosureGroup) {
        this.#deferredRepaint = new DeferredRepaint(contract.window, () => this.#coversThePage())
        this.#drawers = contract.elements('drawer')
            .map((drawer) => new Drawer(contract, drawer, {
                hidden: (left) => disclosures.collapseWithin(left),
                uncovered: () => this.#deferredRepaint.release(),
            }))
        this.#showMoreViews = contract.elements('drawer').map((drawer) => new ShowMoreView(contract, drawer))
    }

    start() {
        this.#drawers.forEach((drawer) => drawer.start())

        return this
    }

    refreshShowMore() {
        this.#showMoreViews.forEach((view) => view.show())
    }

    repaintBehindDrawer(paint: () => void) {
        this.#deferredRepaint.paint(paint)
    }

    #coversThePage() {
        return this.#drawers.some((drawer) => drawer.coversThePage())
    }
}
