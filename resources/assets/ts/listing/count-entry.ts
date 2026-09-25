import { CssTiming } from '../shared/css-timing.ts'

const DURATION = '--meili-duration-fade'
const EASING = '--meili-ease'

/** Not `@starting-style`: it would also play on the first render and when a breakpoint shows the button again. */
export class CountEntry {
    #timing: CssTiming

    constructor(document: Document) {
        this.#timing = new CssTiming(document)
    }

    play(counter: HTMLElement) {
        counter.animate(this.#keyframes(), {
            duration: this.#timing.duration(counter, DURATION) ?? 0,
            easing: this.#timing.easing(counter, EASING),
        })
    }

    #keyframes(): Keyframe[] {
        if (this.#timing.prefersReducedMotion()) {
            return [{ opacity: 0 }, { opacity: 1 }]
        }

        return [{ opacity: 0, transform: 'scale(0.9)' }, { opacity: 1, transform: 'none' }]
    }
}
