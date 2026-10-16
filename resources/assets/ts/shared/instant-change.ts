import { INSTANT } from './attributes.ts'

/** A change the stylesheet shows at once, its transitions cut around it. */
export class InstantChange {
    #node: Element

    constructor(node: Element) {
        this.#node = node
    }

    /** `getAnimations()` flushes the style while the stylesheet cuts the transitions: none starts once the attribute goes. */
    apply(change: () => void) {
        this.#node.setAttribute(INSTANT, '')

        try {
            change()
            this.#node.getAnimations()
        } finally {
            this.#node.removeAttribute(INSTANT)
        }
    }
}
