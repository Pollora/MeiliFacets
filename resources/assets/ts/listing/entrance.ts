import { CssTiming } from '../shared/css-timing.ts'

const DURATION = '--meili-duration-fade'
const EASING = '--meili-ease'

/**
 * A small element that appears — a badge, an active value — faded and scaled a touch up.
 * Not `@starting-style`: it would also play on the first render, when a breakpoint shows the element again,
 * and on every node a redraw rebuilds.
 */
export class Entrance {
    #timing: CssTiming
    #from: string

    /** `scale` is where the element starts from: never 0, nothing in the world comes out of nowhere. */
    constructor(document: Document, scale: number) {
        this.#timing = new CssTiming(document)
        this.#from = `scale(${scale})`
    }

    play(element: HTMLElement) {
        element.animate(this.#keyframes(), {
            duration: this.#timing.duration(element, DURATION) ?? 0,
            easing: this.#timing.easing(element, EASING),
        })
    }

    #keyframes(): Keyframe[] {
        if (this.#timing.prefersReducedMotion()) {
            return [{ opacity: 0 }, { opacity: 1 }]
        }

        return [{ opacity: 0, transform: this.#from }, { opacity: 1, transform: 'none' }]
    }
}
