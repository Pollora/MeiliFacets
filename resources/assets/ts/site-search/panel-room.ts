const ROOM = '--meili-search-room'

/**
 * The height left under the panel's top edge, written where the stylesheet
 * reads it while the panel is open, and taken back once it closes.
 */
export class PanelRoom {
    #panel: HTMLElement

    constructor(panel: HTMLElement) {
        this.#panel = panel
    }

    measure() {
        const viewport = this.#panel.ownerDocument.defaultView?.innerHeight ?? 0
        const room = Math.max(0, viewport - this.#top())

        this.#panel.style.setProperty(ROOM, `${room}px`)
    }

    release() {
        this.#panel.style.removeProperty(ROOM)
    }

    /** From its anchor rather than its own box: the panel is measured while it still slides in, its box offset by its transform. */
    #top() {
        const anchor = this.#panel.offsetParent

        if (!(anchor instanceof Element)) {
            return this.#panel.getBoundingClientRect().top
        }

        return anchor.getBoundingClientRect().top + anchor.clientTop + this.#panel.offsetTop
    }
}
