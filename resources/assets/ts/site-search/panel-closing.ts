import { OPEN } from '../shared/attributes.ts'

/** The loader opens and closes the panel: the client learns of a closing from the mark it takes off the root. */
export class PanelClosing {
    #root: Element

    constructor(root: Element) {
        this.#root = root
    }

    observe(closed: () => void) {
        const view = this.#root.ownerDocument.defaultView

        if (view === null) {
            return
        }

        new view.MutationObserver(() => this.#afterChange(closed)).observe(this.#root, { attributes: true, attributeFilter: [OPEN] })
    }

    #afterChange(closed: () => void) {
        if (!this.#root.hasAttribute(OPEN)) {
            closed()
        }
    }
}
