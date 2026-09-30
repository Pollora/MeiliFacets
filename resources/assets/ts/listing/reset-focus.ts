import { Contract } from '../shared/contract.ts'
import { FocusFallback } from './focus-fallback.ts'

/** A reset hides itself once pressed: the focus it held lands on the next thing to do, never on `body`. */
export class ResetFocus {
    #contract: Contract
    #focusFallback: FocusFallback

    constructor(contract: Contract) {
        this.#contract = contract
        this.#focusFallback = new FocusFallback(contract.root)
    }

    landFrom(reset: Element) {
        if (reset.matches('[hidden]') && this.#focusFallback.isLost(reset)) {
            this.#land(reset)
        }
    }

    #land(reset: Element) {
        const target = this.#applyBeside(reset) ?? this.#sheetTitleOver(reset) ?? this.#focusFallback.target()

        target?.focus({ preventScroll: true })
    }

    /** « Apply » from the same drawer, or from the listing when the reset sits in none. */
    #applyBeside(reset: Element) {
        const within = reset.closest(Contract.selector('drawer')) ?? this.#contract.root
        const shown = this.#contract.all('apply', within).find((apply) => apply instanceof HTMLElement && apply.checkVisibility())

        return shown instanceof HTMLElement ? shown : null
    }

    #sheetTitleOver(reset: Element) {
        const drawer = reset.closest(Contract.selector('drawer'))
        const title = drawer?.getAttribute('aria-modal') === 'true' ? this.#contract.one('drawer-title', drawer) : null

        return title instanceof HTMLElement ? title : null
    }
}
