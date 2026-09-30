const NOT_IN_TAB_ORDER = -1

/** Where a focus lands once the control holding it is gone: somewhere the visitor can go on from, never `body`. */
export class FocusFallback {
    #root: Element

    constructor(root: Element) {
        this.#root = root
    }

    /** A browser that does not focus a clicked button leaves the focus on `body`: that too is lost. */
    isLost(from: Element) {
        const focused = from.ownerDocument.activeElement

        return focused === null || focused === from.ownerDocument.body || from.contains(focused)
    }

    /** The listing itself, made focusable when the theme did not. */
    target() {
        if (!(this.#root instanceof HTMLElement)) {
            return null
        }

        if (!this.#root.hasAttribute('tabindex')) {
            this.#root.tabIndex = NOT_IN_TAB_ORDER
        }

        return this.#root
    }
}
