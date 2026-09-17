import { Contract } from '../shared/contract.ts'
import { PRICE_BOUNDS } from './price-bound.ts'

import type { Range } from '../shared/range.ts'
import type { PriceBound } from './price-bound.ts'

export class PriceInputs {
    #inputs: Record<PriceBound, HTMLInputElement | null> = { min: null, max: null }
    #shown: Record<PriceBound, string> = { min: '', max: '' }
    #block: HTMLElement | null

    constructor(contract: Contract) {
        for (const bound of PRICE_BOUNDS) {
            const input = contract.one(`price-${bound}`)

            this.#inputs[bound] = input instanceof HTMLInputElement ? input : null
            this.#shown[bound] = this.#inputs[bound]?.value ?? ''
        }

        const block = (this.#inputs.min ?? this.#inputs.max)?.closest(Contract.selector('facet'))

        this.#block = block instanceof HTMLElement ? block : null
    }

    get block() {
        return this.#block
    }

    renderedBound(bound: PriceBound) {
        return this.#inputs[bound]?.getAttribute(bound)
    }

    onChange(listener: () => void) {
        PRICE_BOUNDS.forEach((bound) => this.#inputs[bound]?.addEventListener('change', listener))
    }

    fill(bound: PriceBound, value: string) {
        const input = this.#inputs[bound]

        if (input !== null) {
            input.value = value
            this.#shown[bound] = value
        }
    }

    fillBoth(min: number, max: number) {
        this.fill('min', String(min))
        this.fill('max', String(max))
    }

    restore() {
        PRICE_BOUNDS.forEach((bound) => this.fill(bound, this.#shown[bound]))
    }

    asked(bound: PriceBound, bounds: Range): number | null {
        const written = this.#inputs[bound]?.value ?? ''
        const value = written === '' ? null : Number.parseFloat(written)

        return value === null || this.#isEdge(bound, value, bounds) ? null : value
    }

    writeBounds(bounds: Range) {
        for (const bound of PRICE_BOUNDS) {
            const input = this.#inputs[bound]

            if (input !== null && input.type !== 'hidden') {
                input.min = String(bounds.min ?? '')
                input.max = String(bounds.max ?? '')
                input.placeholder = String(bounds[bound] ?? '')
            }
        }
    }

    #isEdge(bound: PriceBound, value: number, bounds: Range) {
        const edge = bounds[bound]

        if (edge === null) {
            return false
        }

        return bound === 'min' ? value <= edge : value >= edge
    }
}
