import { BEYOND_ROW, CONTROLS } from '../shared/attributes.ts'
import { Contract } from '../shared/contract.ts'

/** « Show more » over a drawer's row, shown only while a filter past the row has something to offer. */
export class ShowMoreView {
    #contract: Contract
    #drawer: Element

    constructor(contract: Contract, drawer: Element) {
        this.#contract = contract
        this.#drawer = drawer
    }

    show() {
        const hasMore = this.#beyondRow().some((facet) => !facet.hidden)

        for (const button of this.#buttons()) {
            button.hidden = !hasMore
        }
    }

    #beyondRow() {
        return [...this.#drawer.querySelectorAll<HTMLElement>(`${Contract.selector('facet')}[${BEYOND_ROW}]`)]
    }

    /** Looked up in the listing: while the drawer is the side sheet, « Show more » stands in the row before it. */
    #buttons() {
        return this.#contract.elements('drawer-show-more').filter((button) => button.getAttribute(CONTROLS) === this.#drawer.id)
    }
}
