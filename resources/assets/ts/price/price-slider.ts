import { isDrawn } from './drawn.ts'
import { PRICE_BOUNDS } from './price-bound.ts'

import type { Contract } from '../shared/contract.ts'
import type { Range } from '../shared/range.ts'
import type { Drawn } from './drawn.ts'
import type { Money } from './money.ts'
import type { PriceBound } from './price-bound.ts'

const VALUE_NOW = 'aria-valuenow'
const VALUE_TEXT = 'aria-valuetext'
const VALUE_MIN = 'aria-valuemin'
const VALUE_MAX = 'aria-valuemax'
const BOUND = 'data-bound'

export class PriceSlider {
    #contract: Contract
    #money: Money
    #handles: Record<PriceBound, Drawn | null> = { min: null, max: null }
    #boundLabels: Record<PriceBound, Element | null> = { min: null, max: null }
    #fill: Drawn | null
    #readout: Element | null

    constructor(contract: Contract, money: Money) {
        const fill = contract.one('price-range')

        this.#contract = contract
        this.#money = money
        this.#fill = isDrawn(fill) ? fill : null
        this.#readout = contract.one('price-readout')
        this.#findHandles()
        PRICE_BOUNDS.forEach((bound) => {
            this.#boundLabels[bound] = contract.one(`price-bounds-${bound}`)
        })
    }

    get isRendered() {
        return this.#fill !== null
    }

    handle(bound: PriceBound) {
        return this.#handles[bound]
    }

    boundOf(handle: Element): PriceBound {
        return handle.getAttribute(BOUND) === 'min' ? 'min' : 'max'
    }

    renderedBound(bound: PriceBound) {
        return this.#handles[bound]?.getAttribute(bound === 'min' ? VALUE_MIN : VALUE_MAX)
    }

    nowOf(handle: Element | null) {
        return Number.parseFloat(handle?.getAttribute(VALUE_NOW) || '0')
    }

    describe(shown: Range, bounds: Range) {
        this.#describeHandle('min', shown.min, { floor: bounds.min, ceiling: shown.max })
        this.#describeHandle('max', shown.max, { floor: shown.min, ceiling: bounds.max })
    }

    paint(shown: Range, bounds: Range) {
        this.#fill?.style.setProperty('--from', String(bounds.ratio(shown.min)))
        this.#fill?.style.setProperty('--to', String(bounds.ratio(shown.max)))
        this.#handles.min?.style.setProperty('--at', String(bounds.ratio(shown.min)))
        this.#handles.max?.style.setProperty('--at', String(bounds.ratio(shown.max)))

        if (this.#readout !== null && shown.min !== null && shown.max !== null) {
            this.#readout.textContent = `${this.#money.of(shown.min)} – ${this.#money.of(shown.max)}`
        }
    }

    writeBounds(bounds: Range) {
        for (const bound of PRICE_BOUNDS) {
            const label = this.#boundLabels[bound]
            const value = bounds[bound]

            if (label !== null && value !== null) {
                label.textContent = this.#money.of(value)
            }
        }
    }

    #findHandles() {
        for (const handle of this.#contract.all('price-handle')) {
            const bound = PRICE_BOUNDS.find((candidate) => candidate === handle.getAttribute(BOUND))

            if (bound !== undefined && isDrawn(handle)) {
                this.#handles[bound] = handle
            }
        }
    }

    #describeHandle(bound: PriceBound, value: number | null, { floor, ceiling }: { floor: number | null, ceiling: number | null }) {
        const handle = this.#handles[bound]

        if (handle === null || value === null) {
            return
        }

        const written = this.#money.of(value)

        this.#setAttribute(handle, VALUE_NOW, String(value))
        this.#setAttribute(handle, VALUE_TEXT, written)
        this.#setAttribute(handle, VALUE_MIN, String(floor ?? ''))
        this.#setAttribute(handle, VALUE_MAX, String(ceiling ?? ''))

        const tip = this.#contract.one('price-tip', handle)

        if (tip !== null && tip.textContent !== written) {
            tip.textContent = written
        }
    }

    /** Rewriting an unchanged `aria-valuetext` can make a screen reader announce it again. */
    #setAttribute(element: Element, name: string, value: string) {
        if (element.getAttribute(name) !== value) {
            element.setAttribute(name, value)
        }
    }
}
