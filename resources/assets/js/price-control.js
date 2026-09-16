import { Contract } from './contract.js'
import { Money } from './money.js'

/**
 * @import { PriceBounds } from './price-bounds.js'
 * @import { ListingDescription } from './description.js'
 * @import { ListingState } from './listing-state.js'
 */

const VALUE_NOW = 'aria-valuenow'
const VALUE_TEXT = 'aria-valuetext'
const VALUE_MIN = 'aria-valuemin'
const VALUE_MAX = 'aria-valuemax'
const BOUND = 'data-bound'
const ACTIVE_HANDLE = 'data-active'

/** Keyed by end, never by position: a missing input would shift the other one's meaning. */
const ENDS = ['min', 'max']

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

    /** @type {ListingDescription['priceFields']} */
    #fields

    /** @type {HTMLElement | null} */
    #track = null

    /** @type {HTMLElement | null} */
    #range = null

    /** @type {HTMLElement | null} */
    #readout = null

    /** @type {HTMLElement | null} */
    #block = null

    /** @type {Record<string, HTMLButtonElement | null>} */
    #handles = { min: null, max: null }

    /** @type {Record<string, HTMLInputElement | null>} */
    #inputs = { min: null, max: null }

    /** @type {Record<string, HTMLElement | null>} */
    #boundLabels = { min: null, max: null }

    /** @type {Span} */
    #bounds = { min: 0, max: 0 }

    /** @type {HTMLElement | null} */
    #dragging = null

    /** @type {{ span: Span | null } | null} */
    #pending = null

    /** @type {Record<string, string>} */
    #shown = { min: '', max: '' }

    /**
     * @param {Contract} contract
     * @param {ListingDescription} description
     * @param {(min: number | null, max: number | null) => void} commit
     */
    constructor(contract, description, commit) {
        this.#contract = contract
        this.#money = new Money(description.money ?? null)
        this.#fields = description.priceFields ?? null
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
            min: this.#clamped(state.price.min ?? this.#bounds.min),
            max: this.#clamped(state.price.max ?? this.#bounds.max),
        }

        for (const end of ENDS) {
            this.#fill(end, this.#fieldValue(state, end, held[end]))
        }

        this.#describe(held.min, held.max)
        this.#paint(held.min, held.max)
    }

    /**
     * @param {PriceBounds} bounds
     * @param {ListingState} state
     */
    showBounds(bounds, state) {
        const span = bounds.of(this.#fields)

        // A repaint under a held handle would pull it back to the last committed range.
        if (this.#dragging !== null) {
            this.#pending = { span }

            return
        }

        if (this.#receive(span)) {
            this.show(state)
        }
    }

    // Looked up once: a drag reaches for these on every pointer move.
    #find() {
        this.#range = this.#contract.one('price-range')
        this.#track = this.#contract.one('price-track')
        this.#readout = this.#contract.one('price-readout')

        for (const handle of this.#contract.all('price-handle')) {
            this.#handles[handle.getAttribute(BOUND) ?? ''] = handle
        }

        for (const end of ENDS) {
            this.#inputs[end] = this.#contract.one(`price-${end}`)
            this.#boundLabels[end] = this.#contract.one(`price-bounds-${end}`)
            this.#shown[end] = this.#inputs[end]?.value ?? ''
        }

        this.#block = (this.#inputs.min ?? this.#inputs.max)?.closest(Contract.selector('facet')) ?? null
        this.#bounds = this.#renderedBounds()
    }

    #listen() {
        for (const end of ENDS) {
            const handle = this.#handles[end]

            this.#inputs[end]?.addEventListener('change', () => this.#commitFields())
            handle?.addEventListener('pointerdown', (event) => this.#grab(handle, event))
            handle?.addEventListener('keydown', (event) => this.#stepped(handle, event))
        }

        this.#track?.addEventListener('pointermove', (event) => this.#drag(event))
        this.#track?.addEventListener('pointerup', () => this.#release())
        this.#track?.addEventListener('pointercancel', () => this.#release())
    }

    /**
     * Unknown bounds stay open, so no bound is mistaken for an edge.
     *
     * @returns {Span}
     */
    #renderedBounds() {
        return {
            min: this.#number(this.#handles.min?.getAttribute(VALUE_MIN) ?? this.#inputs.min?.getAttribute('min'), -Infinity),
            max: this.#number(this.#handles.max?.getAttribute(VALUE_MAX) ?? this.#inputs.max?.getAttribute('max'), Infinity),
        }
    }

    /**
     * @param {string | null | undefined} written
     * @param {number} unknown
     */
    #number(written, unknown) {
        const value = Number.parseFloat(written ?? '')

        return Number.isFinite(value) ? value : unknown
    }

    /**
     * @param {Span | null} span
     * @returns {boolean} whether the rail now spans something it did not
     */
    #receive(span) {
        if (this.#block !== null) {
            this.#block.hidden = span === null
        }

        if (span === null || (span.min === this.#bounds.min && span.max === this.#bounds.max)) {
            return false
        }

        this.#bounds = span
        this.#writeBounds(span)

        return true
    }

    #hasSlider() {
        return this.#range !== null
    }

    /**
     * Beside the control a field always shows a figure; alone, an empty one is an open end.
     *
     * @param {ListingState} state
     * @param {string} end
     * @param {number} mirrored
     */
    #fieldValue(state, end, mirrored) {
        if (this.#hasSlider()) {
            return String(mirrored)
        }

        const asked = end === 'min' ? state.price.min : state.price.max

        return asked === null ? '' : String(asked)
    }

    #commitFields() {
        const [min, max] = ENDS.map((end) => this.#asked(end))

        if (min !== null && max !== null && min > max) {
            ENDS.forEach((end) => this.#fill(end, this.#shown[end]))

            return
        }

        this.#commit(min, max)
    }

    /**
     * @param {string} end
     * @returns {number | null}
     */
    #asked(end) {
        const written = this.#inputs[end]?.value ?? ''
        const value = written === '' ? null : Number.parseFloat(written)

        if (value === null) {
            return null
        }

        return (end === 'min' ? value <= this.#bounds.min : value >= this.#bounds.max) ? null : value
    }

    /**
     * @param {HTMLElement} handle
     * @param {PointerEvent} event
     */
    #grab(handle, event) {
        this.#hold(handle)
        this.#track?.setPointerCapture(event.pointerId)
    }

    /**
     * `:active` stays on the handle that was pressed, while a crossing hands the drag to the other.
     *
     * @param {HTMLElement | null} handle
     */
    #hold(handle) {
        this.#dragging?.removeAttribute(ACTIVE_HANDLE)
        this.#dragging = handle
        handle?.setAttribute(ACTIVE_HANDLE, '')
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

        this.#moveTo(this.#dragging, this.#bounds.min + ratio * this.#span())
    }

    #release() {
        if (this.#dragging === null) {
            return
        }

        this.#hold(null)

        if (this.#pending !== null) {
            this.#receive(this.#pending.span)
            this.#pending = null
        }

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
            Home: this.#bounds.min,
            End: this.#bounds.max,
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
        const value = Math.round(this.#clamped(to))

        // Clamping a handle against the other pins both once they meet. They may
        // cross instead, and the one that does takes the end it crossed into.
        const crossed = grabbed === 'min' ? value > other : value < other

        const [low, high] = [Math.min(value, other), Math.max(value, other)]

        this.#describe(low, high)
        this.#write(low, high)
        this.#paint(low, high)

        if (crossed && this.#dragging !== null) {
            this.#hold(this.#handles[grabbed === 'min' ? 'max' : 'min'])
        }
    }

    /**
     * @param {number} min
     * @param {number} max
     */
    #describe(min, max) {
        this.#describeHandle('min', min, this.#bounds.min, max)
        this.#describeHandle('max', max, min, this.#bounds.max)
    }

    /**
     * @param {string} end
     * @param {number} value
     * @param {number} floor
     * @param {number} ceiling
     */
    #describeHandle(end, value, floor, ceiling) {
        const handle = this.#handles[end]

        if (handle === null) {
            return
        }

        const written = this.#money.of(value)

        this.#setAttribute(handle, VALUE_NOW, String(value))
        this.#setAttribute(handle, VALUE_TEXT, written)
        this.#setAttribute(handle, VALUE_MIN, String(floor))
        this.#setAttribute(handle, VALUE_MAX, String(ceiling))

        const tip = this.#contract.one('price-tip', handle)

        if (tip !== null && tip.textContent !== written) {
            tip.textContent = written
        }
    }

    /**
     * Rewriting an unchanged `aria-valuetext` can make a screen reader announce it again.
     *
     * @param {Element} element
     * @param {string} name
     * @param {string} value
     */
    #setAttribute(element, name, value) {
        if (element.getAttribute(name) !== value) {
            element.setAttribute(name, value)
        }
    }

    /**
     * @param {number} min
     * @param {number} max
     */
    #write(min, max) {
        this.#fill('min', String(min))
        this.#fill('max', String(max))
    }

    /**
     * @param {string} end
     * @param {string} value
     */
    #fill(end, value) {
        const input = this.#inputs[end]

        if (input !== null) {
            input.value = value
            this.#shown[end] = value
        }
    }

    /**
     * @param {Span} span
     */
    #writeBounds(span) {
        for (const end of ENDS) {
            const label = this.#boundLabels[end]
            const input = this.#inputs[end]

            if (label !== null) {
                label.textContent = this.#money.of(span[end])
            }

            if (input !== null && input.type !== 'hidden') {
                input.min = String(span.min)
                input.max = String(span.max)
                input.placeholder = String(span[end])
            }
        }
    }

    /**
     * @param {number} value
     */
    #clamped(value) {
        return Math.min(Math.max(value, this.#bounds.min), this.#bounds.max)
    }

    /** @returns {Span} */
    #held() {
        return {
            min: Number.parseFloat(this.#handles.min?.getAttribute(VALUE_NOW) || '0'),
            max: Number.parseFloat(this.#handles.max?.getAttribute(VALUE_NOW) || '0'),
        }
    }

    #span() {
        return this.#bounds.max - this.#bounds.min
    }

    /**
     * @param {number} min
     * @param {number} max
     */
    #paint(min, max) {
        const span = this.#span()
        const ratio = (value) =>
            span <= 0 ? 0 : Math.min(Math.max((value - this.#bounds.min) / span, 0), 1)

        this.#range?.style.setProperty('--from', String(ratio(min)))
        this.#range?.style.setProperty('--to', String(ratio(max)))
        this.#handles.min?.style.setProperty('--at', String(ratio(min)))
        this.#handles.max?.style.setProperty('--at', String(ratio(max)))

        if (this.#readout !== null) {
            this.#readout.textContent = `${this.#money.of(min)} – ${this.#money.of(max)}`
        }
    }
}
