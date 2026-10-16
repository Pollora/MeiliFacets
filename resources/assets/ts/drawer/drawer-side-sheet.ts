import { DrawerRow } from './drawer-row.ts'

import type { Contract } from '../shared/contract.ts'

const OVERFLOW = 'data-overflow'
const SIDE_SHEET_OVERFLOW = 'side-sheet'
const SHOW_MORE_OVERFLOW = 'show-more'
const SIDE_SHEET = 'data-side-sheet'

export class DrawerSideSheet {
    #drawer: HTMLElement
    #sheet: Element
    #window: Window
    #row: DrawerRow

    constructor(drawer: HTMLElement, contract: Contract) {
        this.#drawer = drawer
        this.#sheet = contract.one('drawer-sheet', drawer) ?? drawer
        this.#window = contract.window
        this.#row = new DrawerRow(drawer, contract)
    }

    start() {
        if (this.#overflowsTo(SIDE_SHEET_OVERFLOW)) {
            this.#drawer.setAttribute(SIDE_SHEET, '')
        }
    }

    isSideSheet() {
        return this.#drawer.hasAttribute(SIDE_SHEET)
    }

    isRowLifted() {
        return this.#overflowsTo(SHOW_MORE_OVERFLOW) && this.isSideSheet()
    }

    liftRow() {
        this.#row.lift()
        this.#drawer.setAttribute(SIDE_SHEET, '')
        // Without a style flush, the sheet would start its way in already in place.
        void this.#window.getComputedStyle(this.#sheet).transform
    }

    putBackRow() {
        this.#drawer.removeAttribute(SIDE_SHEET)
        this.#row.putBack()
    }

    #overflowsTo(overflow: string) {
        return this.#drawer.getAttribute(OVERFLOW) === overflow
    }
}
