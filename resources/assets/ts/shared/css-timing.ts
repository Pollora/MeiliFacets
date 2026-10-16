export const REDUCED_MOTION = '(prefers-reduced-motion: reduce)'

const MILLISECONDS = 'ms'
const PER_SECOND = 1000
const DEFAULT_EASING = 'ease-out'

/** The motion the stylesheet declares for an element, read through its custom properties. */
export class CssTiming {
    #view: Window | null
    #reducedMotion: MediaQueryList | null = null

    constructor(document: Document) {
        this.#view = document.defaultView
    }

    /** In milliseconds, whether the theme wrote `ms` or `s`; `null` when the property holds no length. */
    duration(element: Element, property: string) {
        const declared = this.#declared(element, property)
        const amount = Number.parseFloat(declared)

        if (Number.isNaN(amount)) {
            return null
        }

        return declared.endsWith(MILLISECONDS) ? amount : amount * PER_SECOND
    }

    easing(element: Element, property: string) {
        return this.#declared(element, property) || DEFAULT_EASING
    }

    prefersReducedMotion() {
        this.#reducedMotion ??= this.#view?.matchMedia(REDUCED_MOTION) ?? null

        return this.#reducedMotion?.matches ?? false
    }

    #declared(element: Element, property: string) {
        return this.#view?.getComputedStyle(element).getPropertyValue(property).trim() ?? ''
    }
}
