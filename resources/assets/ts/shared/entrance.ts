import { CssTiming } from './css-timing.ts'

/** How an element comes in: where from, and the stylesheet's variables that time it. */
export interface EntranceStyle {
    /** A transform, never `scale(0)`: nothing in the world comes out of nowhere. */
    from: string
    duration: string
    easing: string
}

export interface EntranceTiming {
    duration: number
    easing: string
}

/**
 * An element that appears — a badge, an active value, a section, a search result — faded in from `from`, and
 * only faded under reduced motion. By WAAPI, not `@starting-style`: that would also play on the first render,
 * when a breakpoint shows the element again, and on every node a redraw rebuilds.
 */
export class Entrance {
    #timing: CssTiming
    #style: EntranceStyle

    constructor(document: Document, style: EntranceStyle) {
        this.#timing = new CssTiming(document)
        this.#style = style
    }

    /** Read ahead by a caller that writes several nodes at once: a read between two writes would force a style pass. */
    timingOf(element: Element): EntranceTiming {
        return {
            duration: this.#timing.duration(element, this.#style.duration) ?? 0,
            easing: this.#timing.easing(element, this.#style.easing),
        }
    }

    /** A timing given in part, such as a section's duration measured by its height, is completed from the stylesheet. */
    play(element: HTMLElement, timing: Partial<EntranceTiming> = {}) {
        return element.animate(this.#keyframes(), this.#complete(element, timing))
    }

    #complete(element: Element, { duration, easing }: Partial<EntranceTiming>): EntranceTiming {
        if (duration !== undefined && easing !== undefined) {
            return { duration, easing }
        }

        const declared = this.timingOf(element)

        return { duration: duration ?? declared.duration, easing: easing ?? declared.easing }
    }

    #keyframes(): Keyframe[] {
        if (this.#timing.prefersReducedMotion()) {
            return [{ opacity: 0 }, { opacity: 1 }]
        }

        return [{ opacity: 0, transform: this.#style.from }, { opacity: 1, transform: 'none' }]
    }
}
