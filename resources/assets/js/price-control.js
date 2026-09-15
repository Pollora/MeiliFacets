import { Money } from './money.js'

/**
 * @import { Contract } from './contract.js'
 * @import { ListingDescription } from './description.js'
 * @import { ListingState } from './listing-state.js'
 */

const VALUE_NOW = 'aria-valuenow'
const VALUE_TEXT = 'aria-valuetext'
const BOUND = 'data-bound'

/** Keyed by bound, never by position: a missing input would shift the other one's meaning. */
const BOUNDS = ['min', 'max']

/** @typedef {{ min: number, max: number }} Span */

/**
 * The two inputs the server rendered, plus the range control it may have rendered
 * beside them. The inputs are the filter; the control is a way to move them.
 */
export class PriceControl {
    /** @type {Contract} */
    #contract

    /** @type {(min: number | null, max: number | null) => void} */
    #commit

    /** @type {Money} */
    #money

    /** @type {HTMLElement | null} */
    #track = null

    /** @type {HTMLElement | null} */
    #range = null

    /** @type {HTMLElement | null} */
    #readout = null

    /** @type {Record<string, HTMLButtonElement | null>} */
    #handles = { min: null, max: null }

    /** @type {Record<string, HTMLInputElement | null>} */
    #inputs = { min: null, max: null }

    /** @type {Span} */
    #reachable = { min: 0, max: 0 }

    /** @type {HTMLElement | null} */
    #dragging = null

    /**
     * @param {Contract} contract
     * @param {ListingDescription} description
     * @param {(min: number | null, max: number | null) => void} commit
     */
    constructor(contract, description, commit) {
        this.#contract = contract
        this.#money = new Money(description.money ?? null)
        this.#commit = commit
    }

    start() {
        this.#find()
        this.#listen()
    }

    /**
     * @param {ListingState} state
     */
    show(state) {
        const held = {
            min: state.price.min ?? this.#reachable.min,
            max: state.price.max ?? this.#reachable.max,
        }

        for (const bound of BOUNDS) {
            const input = this.#inputs[bound]

            if (input !== null) {
                input.value = this.#fieldValue(state, bound, held[bound])
            }
        }

        this.#paint(held.min, held.max)
    }

    // Looked up once: a drag reaches for these on every pointer move.
    #find() {
        this.#range = this.#contract.one('price-range')
        this.#track = this.#contract.one('price-track')
        this.#readout = this.#contract.one('price-readout')

        for (const handle of this.#contract.all('price-handle')) {
            this.#handles[handle.getAttribute(BOUND) ?? ''] = handle
        }

        for (const bound of BOUNDS) {
            this.#inputs[bound] = this.#contract.one(`price-${bound}`)
        }

        this.#reachable = this.#reachableBounds()
    }

    #listen() {
        for (const bound of BOUNDS) {
            const handle = this.#handles[bound]

            this.#inputs[bound]?.addEventListener('change', () => this.#commitFields())
            handle?.addEventListener('pointerdown', (event) => this.#grab(handle, event))
            handle?.addEventListener('keydown', (event) => this.#stepped(handle, event))
        }

        this.#track?.addEventListener('pointermove', (event) => this.#drag(event))
        this.#track?.addEventListener('pointerup', () => this.#release())
        this.#track?.addEventListener('pointercancel', () => this.#release())
    }

    /** @returns {Span} */
    #reachableBounds() {
        return {
            min: Number.parseFloat(this.#handles.min?.getAttribute('aria-valuemin') || '0'),
            max: Number.parseFloat(this.#handles.max?.getAttribute('aria-valuemax') || '0'),
        }
    }

    #hasSlider() {
        return this.#range !== null
    }

    /**
     * Beside the control a field always shows a figure; alone, an empty one is an open end.
     *
     * @param {ListingState} state
     * @param {string} bound
     * @param {number} mirrored
     */
    #fieldValue(state, bound, mirrored) {
        if (this.#hasSlider()) {
            return String(mirrored)
        }

        const asked = bound === 'min' ? state.price.min : state.price.max

        return asked === null ? '' : String(asked)
    }

    #commitFields() {
        const [min, max] = BOUNDS.map((bound) => {
            const written = this.#inputs[bound]?.value ?? ''

            return written === '' ? null : Number.parseFloat(written)
        })

        this.#commit(min ?? null, max ?? null)
    }

    /**
     * @param {HTMLElement} handle
     * @param {PointerEvent} event
     */
    #grab(handle, event) {
        this.#dragging = handle
        this.#track?.setPointerCapture(event.pointerId)
    }

    /**
     * @param {PointerEvent} event
     */
    #drag(event) {
        if (this.#dragging === null || this.#track === null) {
            return
        }

        const box = this.#track.getBoundingClientRect()
        const ratio = Math.min(Math.max((event.clientX - box.left) / box.width, 0), 1)

        this.#moveTo(this.#dragging, this.#reachable.min + ratio * this.#span())
    }

    #release() {
        if (this.#dragging === null) {
            return
        }

        this.#dragging = null
        this.#commitFields()
    }

    /**
     * A slider answers the arrows, Home and End — the keyboard pattern its role promises.
     *
     * @param {HTMLElement} handle
     * @param {KeyboardEvent} event
     */
    #stepped(handle, event) {
        const to = this.#steppedTo(handle, event)

        if (to === undefined) {
            return
        }

        event.preventDefault()
        this.#moveTo(handle, to)
        this.#commitFields()
    }

    /**
     * @param {HTMLElement} handle
     * @param {KeyboardEvent} event
     * @returns {number | undefined}
     */
    #steppedTo(handle, event) {
        const now = Number.parseFloat(handle.getAttribute(VALUE_NOW) || '0')
        const step = event.shiftKey ? Math.max(this.#span() / 10, 1) : 1

        return {
            ArrowLeft: now - step,
            ArrowDown: now - step,
            ArrowRight: now + step,
            ArrowUp: now + step,
            Home: this.#reachable.min,
            End: this.#reachable.max,
        }[event.key]
    }

    /**
     * @param {HTMLElement} handle
     * @param {number} to
     */
    #moveTo(handle, to) {
        const grabbed = handle.getAttribute(BOUND) === 'min' ? 'min' : 'max'
        const held = this.#held()
        const other = grabbed === 'min' ? held.max : held.min
        const value = Math.round(Math.min(Math.max(to, this.#reachable.min), this.#reachable.max))

        // Clamping a handle against the other pins both once they meet. They may
        // cross instead, and the one that does takes the end it crossed into.
        const crossed = grabbed === 'min' ? value > other : value < other

        this.#showBound('min', Math.min(value, other))
        this.#showBound('max', Math.max(value, other))
        this.#paint(Math.min(value, other), Math.max(value, other))

        if (crossed && this.#dragging !== null) {
            this.#dragging = this.#handles[grabbed === 'min' ? 'max' : 'min']
        }
    }

    /**
     * @param {string} bound
     * @param {number} value
     */
    #showBound(bound, value) {
        const written = this.#money.of(value)
        const handle = this.#handles[bound]

        handle?.setAttribute(VALUE_NOW, String(value))
        handle?.setAttribute(VALUE_TEXT, written)

        const tip = handle === null ? null : this.#contract.one('price-tip', handle)

        if (tip !== null) {
            tip.textContent = written
        }

        const input = this.#inputs[bound]

        if (input !== null) {
            input.value = String(value)
        }
    }

    /** @returns {Span} */
    #held() {
        return {
            min: Number.parseFloat(this.#handles.min?.getAttribute(VALUE_NOW) || '0'),
            max: Number.parseFloat(this.#handles.max?.getAttribute(VALUE_NOW) || '0'),
        }
    }

    #span() {
        return this.#reachable.max - this.#reachable.min
    }

    /**
     * @param {number} min
     * @param {number} max
     */
    #paint(min, max) {
        const span = this.#span()
        const ratio = (value) =>
            span <= 0 ? 0 : Math.min(Math.max((value - this.#reachable.min) / span, 0), 1)

        this.#range?.style.setProperty('--from', String(ratio(min)))
        this.#range?.style.setProperty('--to', String(ratio(max)))
        this.#handles.min?.style.setProperty('--at', String(ratio(min)))
        this.#handles.max?.style.setProperty('--at', String(ratio(max)))

        if (this.#readout !== null) {
            this.#readout.textContent = `${this.#money.of(min)} – ${this.#money.of(max)}`
        }
    }
}
