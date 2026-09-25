import { CssTiming } from './css-timing.ts'

/** How an element comes in: where from, and the stylesheet's variables that time it. */
export interface EntranceStyle {
    /** A transform, never `scale(0)`: nothing in the world comes out of nowhere. */
    from: string
    duration: string
    easing: string
}

/**
 * An element that appears — a badge, an active value, a section — faded in from `from`, and only faded under
 * reduced motion. By WAAPI, not `@starting-style`: that would also play on the first render, when a breakpoint
 * shows the element again, and on every node a redraw rebuilds.
 */
export class Entrance {
    #timing: CssTiming
    #style: EntranceStyle

    constructor(document: Document, style: EntranceStyle) {
        this.#timing = new CssTiming(document)
        this.#style = style
    }

    /** `duration` for a length measured at the time, such as a section's by its height. */
    play(element: HTMLElement, duration = this.#timing.duration(element, this.#style.duration) ?? 0) {
        element.animate(this.#keyframes(), { duration, easing: this.#timing.easing(element, this.#style.easing) })
    }

    #keyframes(): Keyframe[] {
        if (this.#timing.prefersReducedMotion()) {
            return [{ opacity: 0 }, { opacity: 1 }]
        }

        return [{ opacity: 0, transform: this.#style.from }, { opacity: 1, transform: 'none' }]
    }
}
