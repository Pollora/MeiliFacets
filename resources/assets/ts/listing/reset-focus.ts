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
        return this.#contract.elements('apply', within).find((apply) => apply.checkVisibility()) ?? null
    }

    #sheetTitleOver(reset: Element) {
        const drawer = reset.closest(Contract.selector('drawer'))
        return drawer?.getAttribute('aria-modal') === 'true' ? this.#contract.element('drawer-title', drawer) : null
    }
}
