import { Contract } from '../shared/contract.ts'

/** A reset hides itself once pressed: the focus it held lands on the next thing to do, never on `body`. */
export class ResetFocus {
    #contract: Contract

    constructor(contract: Contract) {
        this.#contract = contract
    }

    landFrom(reset: Element) {
        if (reset.matches('[hidden]') && !this.#focusedElsewhere(reset)) {
            this.#land(reset)
        }
    }

    #land(reset: Element) {
        const landing = this.#applyBeside(reset) ?? this.#sheetTitleOver(reset) ?? this.#focusableRoot()

        landing?.focus({ preventScroll: true })
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

    /** A browser that does not focus a clicked button leaves the focus on `body`: that too is lost. */
    #focusedElsewhere(reset: Element) {
        const active = reset.ownerDocument.activeElement

        return active !== null && active !== reset.ownerDocument.body && !reset.contains(active)
    }

    #focusableRoot() {
        const root = this.#contract.root

        if (!(root instanceof HTMLElement)) {
            return null
        }

        if (!root.hasAttribute('tabindex')) {
            root.tabIndex = -1
        }

        return root
    }
}
