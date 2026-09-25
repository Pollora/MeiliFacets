import { Range } from '../shared/range.ts'
import { Money } from './money.ts'
import { PRICE_BOUNDS } from './price-bound.ts'
import { PriceInputs } from './price-inputs.ts'
import { PriceQuery } from './price-query.ts'
import { PriceSlider } from './price-slider.ts'
import { SliderDrag } from './slider-drag.ts'
import { SliderKeys } from './slider-keys.ts'

import type { ListingState } from '../listing/listing-state.ts'
import type { Contract } from '../shared/contract.ts'
import type { ListingDescription } from '../shared/description.ts'
import type { Answers } from '../shared/search-client.ts'
import type { SelectionHolder } from '../collapsible/selected-count-view.ts'
import type { Drawn } from './drawn.ts'
import type { PriceBound } from './price-bound.ts'

type Commit = (min: number | null, max: number | null) => void

/**
 * The two inputs the server rendered, plus the range control it may have rendered
 * beside them. The inputs are the filter; the control is a way to move them.
 */
export class PriceControl implements SelectionHolder {
    #commit: Commit
    #query: PriceQuery | null
    #inputs: PriceInputs
    #slider: PriceSlider
    #drag: SliderDrag
    #bounds = new Range()
    #pending: Range | null = null

    constructor(contract: Contract, description: ListingDescription, commit: Commit) {
        this.#commit = commit
        this.#query = description.priceFields ? new PriceQuery(description.priceFields) : null
        this.#inputs = new PriceInputs(contract)
        this.#slider = new PriceSlider(contract, new Money(description.money ?? null))
        this.#drag = new SliderDrag(contract)
    }

    start() {
        this.#bounds = this.#renderedBounds()
        this.#inputs.onChange(() => this.#commitInputs())
        PRICE_BOUNDS.forEach((bound) => this.#listenTo(this.#slider.handle(bound)))
        this.#drag.onMove((event) => this.#dragged(event))
        this.#drag.onRelease(() => this.#released())
    }

    show(state: ListingState) {
        const shown = new Range(this.#shown(state.price.min, this.#bounds.min), this.#shown(state.price.max, this.#bounds.max))

        for (const bound of PRICE_BOUNDS) {
            this.#inputs.fill(bound, this.#inputValue(state, bound, shown[bound]))
        }

        this.#draw(shown)
    }

    heldIn(block: Element, state: ListingState): number | undefined {
        return block === this.#inputs.block ? state.priceFilterCount() : undefined
    }

    showBounds(answers: Answers, state: ListingState) {
        const bounds = this.#query?.boundsFrom(answers) ?? new Range()

        // A repaint under a held handle would pull it back to the last committed range.
        if (this.#drag.grabbed !== null) {
            this.#pending = bounds

            return
        }

        if (this.#receive(bounds)) {
            this.show(state)
        }
    }

    /** Unknown bounds stay open, so no bound is mistaken for an edge. */
    #renderedBounds() {
        return new Range(
            this.#number(this.#slider.renderedBound('min') ?? this.#inputs.renderedBound('min')),
            this.#number(this.#slider.renderedBound('max') ?? this.#inputs.renderedBound('max')),
        )
    }

    #number(written: string | null | undefined) {
        const value = Number.parseFloat(written ?? '')

        return Number.isFinite(value) ? value : null
    }

    #shown(asked: number | null, bound: number | null) {
        const value = asked ?? bound

        return value === null ? null : this.#bounds.clamp(value)
    }

    #listenTo(handle: Drawn | null) {
        handle?.addEventListener('pointerdown', (event) => this.#drag.grab(handle, event))
        handle?.addEventListener('keydown', (event) => this.#stepped(handle, event))
        handle?.addEventListener('keyup', (event) => this.#steppedOff(handle, event))
    }

    /** @returns whether the bounds changed */
    #receive(bounds: Range): boolean {
        if (this.#inputs.block !== null) {
            this.#inputs.block.hidden = bounds.isEmpty()
        }

        if (bounds.isEmpty() || this.#bounds.equals(bounds)) {
            return false
        }

        this.#bounds = bounds
        this.#slider.writeBounds(bounds)
        this.#inputs.writeBounds(bounds)

        return true
    }

    /** Beside the control a field always shows a figure; alone, an empty one is an open end. */
    #inputValue(state: ListingState, bound: PriceBound, shown: number | null) {
        const value = this.#slider.isRendered ? shown : state.price[bound]

        return value === null ? '' : String(value)
    }

    #commitInputs() {
        const min = this.#inputs.asked('min', this.#bounds)
        const max = this.#inputs.asked('max', this.#bounds)

        if (min !== null && max !== null && min > max) {
            this.#inputs.restore()

            return
        }

        this.#commit(min, max)
    }

    #dragged(event: PointerEvent) {
        const handle = this.#drag.grabbed
        const ratio = this.#drag.ratioAt(event)

        if (handle !== null && ratio !== null) {
            this.#moveTo(handle, this.#bounds.valueAt(ratio))
        }
    }

    #released() {
        if (this.#drag.grabbed === null) {
            return
        }

        this.#drag.release()

        if (this.#pending !== null) {
            this.#receive(this.#pending)
            this.#pending = null
        }

        this.#commitInputs()
    }

    #stepped(handle: Drawn, event: KeyboardEvent) {
        const to = SliderKeys.targetOf(event, this.#slider.nowOf(handle), this.#bounds)

        if (to === undefined) {
            return
        }

        event.preventDefault()
        this.#moveTo(handle, to)
    }

    /** A held key repeats its `keydown`: the range commits once, when the key is let go. */
    #steppedOff(handle: Drawn, event: KeyboardEvent) {
        if (SliderKeys.targetOf(event, this.#slider.nowOf(handle), this.#bounds) !== undefined) {
            this.#commitInputs()
        }
    }

    #moveTo(handle: Drawn, to: number) {
        const grabbed = this.#slider.boundOf(handle)
        const opposite = this.#slider.handle(grabbed === 'min' ? 'max' : 'min')
        const value = Math.round(this.#bounds.clamp(to))

        if (opposite === null) {
            return this.#movedAlone(grabbed, value)
        }

        const other = this.#slider.nowOf(opposite)
        const low = Math.min(value, other)
        const high = Math.max(value, other)

        // Clamping a handle against the other pins both once they meet. They may
        // cross instead, and the one that does takes the bound it crossed into.
        const crossed = grabbed === 'min' ? value > other : value < other

        this.#draw(new Range(low, high))
        this.#inputs.fillBoth(low, high)

        if (crossed && this.#drag.grabbed !== null) {
            this.#drag.handOver(opposite)
        }
    }

    #movedAlone(grabbed: PriceBound, value: number) {
        const edge = grabbed === 'min' ? this.#bounds.max : this.#bounds.min

        // Without the opposite edge there is no range to show: the bounds have not been measured yet.
        if (edge === null) {
            return
        }

        this.#draw(grabbed === 'min' ? new Range(value, edge) : new Range(edge, value))
        this.#inputs.fillBoth(Math.min(value, edge), Math.max(value, edge))
    }

    #draw(shown: Range) {
        this.#slider.describe(shown, this.#bounds)
        this.#slider.paint(shown, this.#bounds)
    }
}
