import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { PriceControl } from '../../resources/assets/ts/price/price-control.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { RESULTS } from '../../resources/assets/ts/shared/plan.ts'
import { CONTRACT, find, open, press, release, stroke } from './dom.ts'
import { described } from './fixtures.ts'

import type { Answers } from '../../resources/assets/ts/shared/search-client.ts'
import type { TestWindow } from './dom.ts'

type Bound = number | null

type Span = { min: number, max: number }

const description = described({
    money: { format: '%2$s %1$s', symbol: '€', decimals: 2, decimal: ',', thousand: ' ' },
    priceFields: { min: 'price.min', max: 'price.max' },
})

const measured = (span: Span | null): Answers => span === null ? {} : {
    [RESULTS]: { facetStats: { 'price.min': { min: span.min, max: span.max }, 'price.max': { min: span.min, max: span.max } } },
}

const markup = ({ min, max, reachable = { min: 0, max: 199 } }: { min: number, max: number, reachable?: Span }) => `
<div data-listing data-meili-contract="${CONTRACT}">
 <fieldset data-meili="facet">
  <div data-meili="price-range">
    <div data-meili="price-track">
      <button data-meili="price-handle" data-bound="min"
              aria-valuemin="${reachable.min}" aria-valuemax="${max}" aria-valuenow="${min}">
        <span data-meili="price-tip"></span>
      </button>
      <button data-meili="price-handle" data-bound="max"
              aria-valuemin="${min}" aria-valuemax="${reachable.max}" aria-valuenow="${max}">
        <span data-meili="price-tip"></span>
      </button>
    </div>
  </div>
  <span data-meili="price-readout"></span>
  <span data-meili="price-bounds-min"></span><span data-meili="price-bounds-max"></span>
  <input data-meili="price-min" name="min_price" value="${min}" min="${reachable.min}" max="${reachable.max}">
  <input data-meili="price-max" name="max_price" value="${max}" min="${reachable.min}" max="${reachable.max}">
 </fieldset>
</div>`

const priced = (min: Bound, max: Bound) => new ListingState({ price: { min, max } })

const started = (root: Element) => {
    const committed: [Bound, Bound][] = []
    const priceControl = new PriceControl(new Contract(root), description, (min, max) => committed.push([min, max]))

    priceControl.start()

    return { priceControl, committed }
}

const typed = (window: TestWindow, root: Element, hook: string, value: string) => {
    const input = find<HTMLInputElement>(root, `[data-meili="${hook}"]`)

    input.value = value
    input.dispatchEvent(new window.Event('change', { bubbles: true }))
}

const control = (state: { min: number, max: number }) => {
    const { window, root } = open(markup(state))
    const { priceControl, committed } = started(root)
    const input = (bound: string) => find<HTMLInputElement>(root, `[data-meili="price-${bound}"]`)
    const handle = (bound: string) => find(root, `[data-bound="${bound}"]`)

    return {
        window,
        committed,
        handle,
        show: (min: Bound, max: Bound) => priceControl.show(priced(min, max)),
        showBounds: (span: Span | null, min: Bound = null, max: Bound = null) => priceControl.showBounds(measured(span), priced(min, max)),
        pointer: (type: string, node: Element) => node.dispatchEvent(new window.PointerEvent(type, { bubbles: true, pointerId: 1, clientX: 0 })),
        track: () => find(root, '[data-meili="price-track"]'),
        block: () => find(root, '[data-meili="facet"]'),
        line: () => [find(root, '[data-meili="price-bounds-min"]').textContent, find(root, '[data-meili="price-bounds-max"]').textContent],
        limits: (bound: string) => [input(bound).min, input(bound).max],
        announced: (bound: string) => [
            handle(bound).getAttribute('aria-valuenow'),
            handle(bound).getAttribute('aria-valuetext'),
            handle(bound).textContent.trim(),
        ],
        held: () => [input('min').value, input('max').value],
        readout: () => find(root, '[data-meili="price-readout"]').textContent,
    }
}

describe('a price range on the keyboard', () => {
    it('takes a tenth of the range at once with Shift', () => {
        const price = control({ min: 55, max: 120 })

        price.handle('max').dispatchEvent(new price.window.KeyboardEvent('keydown', { key: 'ArrowLeft', shiftKey: true, bubbles: true }))

        assert.deepEqual(price.held(), ['55', '100'])
    })

    /** Without a bound rendered anywhere, the ends are unknown: Home and End have nowhere to go. */
    it('leaves a handle where it is when the page rendered no bounds', () => {
        const { window, root } = open(`
            <div data-listing data-meili-contract="${CONTRACT}">
              <fieldset data-meili="facet">
                <div data-meili="price-range"><div data-meili="price-track">
                  <button data-meili="price-handle" data-bound="min" aria-valuenow="20"></button>
                  <button data-meili="price-handle" data-bound="max" aria-valuenow="80"></button>
                </div></div>
                <input data-meili="price-min" name="min_price" value="20">
                <input data-meili="price-max" name="max_price" value="80">
              </fieldset>
            </div>`)
        const { committed } = started(root)
        const handle = find(root, '[data-bound="min"]')

        stroke(window, handle, 'Home')

        assert.equal(handle.getAttribute('aria-valuenow'), '20')
        assert.deepEqual(committed, [])
    })

    it('steps a handle and commits where it landed', () => {
        const price = control({ min: 55, max: 120 })

        stroke(price.window, price.handle('min'), 'ArrowRight')

        assert.deepEqual(price.held(), ['56', '120'])
        assert.deepEqual(price.committed, [[56, 120]])
    })

    it('goes to each end with Home and End', () => {
        const price = control({ min: 55, max: 120 })

        stroke(price.window, price.handle('min'), 'Home')
        assert.deepEqual(price.held(), ['0', '120'])

        stroke(price.window, price.handle('max'), 'End')
        assert.deepEqual(price.held(), ['0', '199'])
    })

    /**
     * Clamping each handle against the other pins both once they meet: at the top
     * the high one could no longer come back down, and at the bottom the low one
     * could not go up.
     */
    it('still moves when both ends sit on the same value', () => {
        const top = control({ min: 199, max: 199 })
        stroke(top.window, top.handle('max'), 'ArrowLeft')
        assert.deepEqual(top.held(), ['198', '199'])

        const bottom = control({ min: 0, max: 0 })
        stroke(bottom.window, bottom.handle('min'), 'ArrowRight')
        assert.deepEqual(bottom.held(), ['0', '1'])
    })

    it('never leaves what the catalogue can reach', () => {
        const price = control({ min: 0, max: 199 })

        stroke(price.window, price.handle('min'), 'ArrowLeft')
        stroke(price.window, price.handle('max'), 'ArrowRight')

        assert.deepEqual(price.held(), ['0', '199'])
    })

    it('writes what the shop writes, in the tip and in the readout', () => {
        const price = control({ min: 55, max: 120 })

        stroke(price.window, price.handle('min'), 'ArrowRight')

        assert.equal(find(price.handle('min'), '[data-meili="price-tip"]').textContent, '56,00 €')
        assert.equal(price.readout(), '56,00 € – 120,00 €')
    })
})

describe('a price range shown a state it did not set itself', () => {
    it('announces the bound a field wrote, not the one the handle last held', () => {
        const price = control({ min: 0, max: 199 })

        price.show(null, 90)

        assert.deepEqual(price.announced('max'), ['90', '90,00 €', '90,00 €'])
    })

    it('steps from the bound a field wrote rather than overwriting it', () => {
        const price = control({ min: 0, max: 199 })

        price.show(null, 90)
        stroke(price.window, price.handle('max'), 'ArrowLeft')

        assert.deepEqual(price.held(), ['0', '89'])
        assert.deepEqual(price.committed, [[null, 89]])
    })

    it('announces the ends again once the range is cleared', () => {
        const price = control({ min: 55, max: 120 })

        price.show(null, null)

        assert.deepEqual(price.announced('min'), ['0', '0,00 €', '0,00 €'])
        assert.deepEqual(price.announced('max'), ['199', '199,00 €', '199,00 €'])
    })

    it('narrows what each handle may reach to where the other one now sits', () => {
        const price = control({ min: 0, max: 199 })

        price.show(40, 90)

        assert.equal(price.handle('min').getAttribute('aria-valuemax'), '90')
        assert.equal(price.handle('max').getAttribute('aria-valuemin'), '40')
    })
})

describe('a price range whose bounds the engine measured again', () => {
    it('lets each handle reach the new ends and no further', () => {
        const price = control({ min: 0, max: 199 })

        price.showBounds({ min: 43, max: 199 })

        assert.equal(price.handle('min').getAttribute('aria-valuemin'), '43')
        stroke(price.window, price.handle('min'), 'Home')
        assert.deepEqual(price.held(), ['43', '199'])
    })

    it('writes the new ends under the rail and on the fields', () => {
        const price = control({ min: 0, max: 199 })

        price.showBounds({ min: 9, max: 47 })

        assert.deepEqual(price.line(), ['9,00 €', '47,00 €'])
        assert.deepEqual(price.limits('min'), ['9', '47'])
    })

    it('draws a held bound inside the new ends, as the server does', () => {
        const price = control({ min: 0, max: 199 })

        price.showBounds({ min: 9, max: 47 }, null, 120)

        assert.deepEqual(price.announced('max'), ['47', '47,00 €', '47,00 €'])
    })

    it('hides the range when nothing is left to measure, and shows it again after', () => {
        const price = control({ min: 0, max: 199 })

        price.showBounds(null)
        assert.equal(price.block().hidden, true)

        price.showBounds({ min: 0, max: 199 })
        assert.equal(price.block().hidden, false)
    })
})

describe('a price range whose bounds arrive while a handle is held', () => {
    it('leaves the held handle where the pointer put it', () => {
        const price = control({ min: 55, max: 120 })

        price.pointer('pointerdown', price.handle('max'))
        price.showBounds({ min: 9, max: 47 }, 55, 120)

        assert.deepEqual(price.announced('max'), ['120', null, ''])
        assert.deepEqual(price.held(), ['55', '120'])
        assert.deepEqual(price.line(), ['', ''])
    })

    it('takes the bounds once the handle is let go', () => {
        const price = control({ min: 55, max: 120 })

        price.pointer('pointerdown', price.handle('max'))
        price.showBounds({ min: 9, max: 199 }, 55, 120)
        price.pointer('pointerup', price.track())

        assert.deepEqual(price.line(), ['9,00 €', '199,00 €'])
        assert.deepEqual(price.committed, [[55, 120]])
    })
})

describe('a price range dragged across itself', () => {
    const dragged = () => {
        const price = control({ min: 55, max: 120 })
        price.track().getBoundingClientRect = () => ({ left: 0, width: 199, top: 0, height: 10 }) as DOMRect

        const at = (type: string, node: Element, clientX: number) => node.dispatchEvent(new price.window.PointerEvent(type, { bubbles: true, pointerId: 1, clientX }))

        return { price, at }
    }

    it('marks the handle being dragged, and only that one', () => {
        const { price, at } = dragged()

        at('pointerdown', price.handle('min'), 55)

        assert.equal(price.handle('min').hasAttribute('data-active'), true)
        assert.equal(price.handle('max').hasAttribute('data-active'), false)
    })

    it('moves the mark to the other handle once they cross', () => {
        const { price, at } = dragged()

        at('pointerdown', price.handle('min'), 55)
        at('pointermove', price.track(), 150)

        assert.deepEqual(price.held(), ['120', '150'])
        assert.equal(price.handle('min').hasAttribute('data-active'), false)
        assert.equal(price.handle('max').hasAttribute('data-active'), true)
    })

    it('clears the mark on release', () => {
        const { price, at } = dragged()

        at('pointerdown', price.handle('min'), 55)
        at('pointerup', price.track(), 55)

        assert.equal(price.handle('min').hasAttribute('data-active'), false)
    })
})

describe('a price bound left on the edge of the rail', () => {
    it('is not a filter, so it is not committed', () => {
        const price = control({ min: 55, max: 120 })

        stroke(price.window, price.handle('max'), 'End')
        stroke(price.window, price.handle('min'), 'Home')

        assert.deepEqual(price.committed, [[55, null], [null, null]])
    })

    it('still commits a bound one step inside the edge', () => {
        const price = control({ min: 0, max: 199 })

        stroke(price.window, price.handle('max'), 'ArrowLeft')

        assert.deepEqual(price.committed, [[null, 198]])
    })

    it('finds the edges on the fields when no rail is drawn', () => {
        const { window, root } = open(`
            <div data-listing data-meili-contract="${CONTRACT}">
              <fieldset data-meili="facet">
                <input data-meili="price-min" type="number" name="min_price" value="" min="9" max="47">
                <input data-meili="price-max" type="number" name="max_price" value="" min="9" max="47">
              </fieldset>
            </div>`)
        const { committed } = started(root)

        typed(window, root, 'price-min', '9')
        typed(window, root, 'price-max', '30')

        assert.deepEqual(committed, [[null, null], [null, 30]])
    })
})

describe('a price bound typed across the other one', () => {
    it('is refused beside a rail, and the field goes back to the range held', () => {
        const { window, root } = open(markup({ min: 55, max: 120 }))
        const { priceControl, committed } = started(root)
        priceControl.show(priced(55, 120))

        typed(window, root, 'price-min', '150')

        assert.deepEqual(committed, [])
        assert.equal(find<HTMLInputElement>(root, '[data-meili="price-min"]').value, '55')
    })

    it('is refused without a rail too, and the field goes back to what it showed', () => {
        const { window, root } = open(`
            <div data-listing data-meili-contract="${CONTRACT}">
              <fieldset data-meili="facet">
                <input data-meili="price-min" type="number" name="min_price" value="" min="0" max="199">
                <input data-meili="price-max" type="number" name="max_price" value="" min="0" max="199">
              </fieldset>
            </div>`)
        const { priceControl, committed } = started(root)
        priceControl.show(priced(null, 30))

        typed(window, root, 'price-min', '80')

        assert.deepEqual(committed, [])
        assert.equal(find<HTMLInputElement>(root, '[data-meili="price-min"]').value, '')
    })

    it('still lets both ends meet on a single price', () => {
        const { window, root } = open(markup({ min: 55, max: 120 }))
        const { committed } = started(root)

        typed(window, root, 'price-min', '120')

        assert.deepEqual(committed, [[120, 120]])
    })
})

describe('a price handle moved from the keyboard', () => {
    it('moves on every press, and commits once the key is let go', () => {
        const price = control({ min: 55, max: 120 })

        for (let i = 0; i < 3; i++) {
            press(price.window, price.handle('min'), 'ArrowRight')
        }

        assert.deepEqual(price.held(), ['58', '120'])
        assert.deepEqual(price.committed, [])

        release(price.window, price.handle('min'), 'ArrowRight')

        assert.deepEqual(price.committed, [[58, 120]])
    })

    it('commits nothing when a key that moves nothing is let go', () => {
        const price = control({ min: 55, max: 120 })

        release(price.window, price.handle('min'), 'Shift')
        release(price.window, price.handle('min'), 'Tab')

        assert.deepEqual(price.committed, [])
    })
})
