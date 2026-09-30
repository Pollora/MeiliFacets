/**
 * A repaint of the page behind the sheet: out of sight, it would only cost the sheet its frames.
 * Held while the page is covered, the latest one only, and run the frame after the page shows again.
 */
export class DeferredRepaint {
    #window: Window
    #covered: () => boolean
    #pending: (() => void) | null = null

    constructor(window: Window, covered: () => boolean) {
        this.#window = window
        this.#covered = covered
    }

    paint(paint: () => void) {
        if (this.#covered()) {
            this.#pending = paint

            return
        }

        this.#pending = null
        paint()
    }

    /** After the next frame: the gesture that uncovered the page is painted first. */
    release() {
        if (this.#pending !== null) {
            this.#window.requestAnimationFrame(() => this.#window.setTimeout(() => this.#flush(), 0))
        }
    }

    #flush() {
        const pending = this.#pending

        if (pending !== null && !this.#covered()) {
            this.#pending = null
            pending()
        }
    }
}
