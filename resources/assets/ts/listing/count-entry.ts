const DURATION = '--meili-duration-fade'
const EASING = '--meili-ease'
const REDUCED_MOTION = '(prefers-reduced-motion: reduce)'

/** Not `@starting-style`: it would also play on the first render and when a breakpoint shows the button again. */
export class CountEntry {
    #view: Window | null

    constructor(document: Document) {
        this.#view = document.defaultView
    }

    play(counter: HTMLElement) {
        const style = this.#view?.getComputedStyle(counter)

        counter.animate(this.#keyframes(), {
            duration: Number.parseFloat(style?.getPropertyValue(DURATION) ?? '') || 0,
            easing: style?.getPropertyValue(EASING).trim() || 'ease-out',
        })
    }

    #keyframes(): Keyframe[] {
        if (this.#view?.matchMedia(REDUCED_MOTION).matches) {
            return [{ opacity: 0 }, { opacity: 1 }]
        }

        return [{ opacity: 0, transform: 'scale(0.9)' }, { opacity: 1, transform: 'none' }]
    }
}
