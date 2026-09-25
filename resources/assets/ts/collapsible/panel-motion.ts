const DURATION = '--meili-duration-content'
const EASING = '--meili-ease-content'
const POP_DURATION = '--meili-duration-panel-in'
const REDUCED_MOTION = '(prefers-reduced-motion: reduce)'

/** |Δh| / 500 in seconds, held between 150 and 270 ms: a small change is quick, a large one a little longer. */
const PER_PIXEL = 2
const SHORTEST = 150
const LONGEST = 270

const EXIT_SHARE = 0.75

const POP_FALLBACK = 180

/** In by WAAPI, timed once the height is known; out by the stylesheet's `[hidden]` transition, timed before. */
export class PanelMotion {
    #view: Window | null

    constructor(document: Document) {
        this.#view = document.defaultView
    }

    show(panel: HTMLElement) {
        panel.hidden = false
        panel.animate(this.#entry(), { duration: this.#durationFor(panel.offsetHeight), easing: this.#easing(panel) })
    }

    pop(panel: HTMLElement) {
        panel.hidden = false
        panel.animate(this.#entry(), { duration: this.#popDuration(panel), easing: this.#easing(panel) })
    }

    /**
     * A running entry would hold the panel over the exit: it ends at once, and the exit starts from the panel shown.
     * Settles once the panel is out, whether its exit ran to the end or was cut short.
     */
    hide(panel: HTMLElement) {
        panel.getAnimations().forEach((animation) => animation.finish())
        panel.style.setProperty(DURATION, `${Math.round(this.#durationFor(panel.offsetHeight) * EXIT_SHARE)}ms`)
        panel.hidden = true

        return Promise.allSettled(panel.getAnimations().map((animation) => animation.finished)).then(() => undefined)
    }

    /** Out of sight already: the panel goes without its exit. */
    drop(panel: HTMLElement) {
        panel.hidden = true
        panel.getAnimations().forEach((animation) => animation.finish())
    }

    #durationFor(height: number) {
        return Math.min(Math.max(height * PER_PIXEL, SHORTEST), LONGEST)
    }

    #popDuration(panel: HTMLElement) {
        const declared = this.#view?.getComputedStyle(panel).getPropertyValue(POP_DURATION).trim() ?? ''
        const amount = Number.parseFloat(declared)

        if (Number.isNaN(amount)) {
            return POP_FALLBACK
        }

        return declared.endsWith('ms') ? amount : amount * 1000
    }

    #entry(): Keyframe[] {
        if (this.#view?.matchMedia(REDUCED_MOTION).matches) {
            return [{ opacity: 0 }, { opacity: 1 }]
        }

        return [{ opacity: 0, transform: 'scale(0.96)' }, { opacity: 1, transform: 'none' }]
    }

    #easing(panel: HTMLElement) {
        return this.#view?.getComputedStyle(panel).getPropertyValue(EASING).trim() || 'ease-out'
    }
}
