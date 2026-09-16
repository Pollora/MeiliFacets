import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/js/contract.js'
import { ListingQuery } from '../../resources/assets/js/listing-query.js'
import { PriceBounds } from '../../resources/assets/js/price-bounds.js'
import { PriceControl } from '../../resources/assets/js/price-control.js'
import { CONTRACT, open, press } from './dom.js'

const description = {
    money: { format: '%2$s %1$s', symbol: '€', decimals: 2, decimal: ',', thousand: ' ' },
    priceFields: { min: 'price.min', max: 'price.max' },
}

const measured = (span) => new PriceBounds(span === null ? {} : {
    [ListingQuery.RESULTS]: { facetStats: { 'price.min': { min: span.min, max: span.max }, 'price.max': { min: span.min, max: span.max } } },
})

const markup = ({ min, max, reachable = { min: 0, max: 199 } }) => `
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

const control = (state) => {
    const { window, root } = open(markup(state))
    const committed = []
    const priceControl = new PriceControl(new Contract(root), description, (min, max) => committed.push([min, max]))

    priceControl.start()

    return {
        window,
        committed,
        show: (min, max) => priceControl.show({ price: { min, max } }),
        showBounds: (span, min = null, max = null) => priceControl.showBounds(measured(span), { price: { min, max } }),
        pointer: (type, node) => node.dispatchEvent(new window.PointerEvent(type, { bubbles: true, pointerId: 1, clientX: 0 })),
        track: () => root.querySelector('[data-meili="price-track"]'),
        block: () => root.querySelector('[data-meili="facet"]'),
        line: () => [root.querySelector('[data-meili="price-bounds-min"]').textContent, root.querySelector('[data-meili="price-bounds-max"]').textContent],
        limits: (bound) => {
            const input = root.querySelector(`[data-meili="price-${bound}"]`)

            return [input.min, input.max]
        },
        announced: (bound) => {
            const handle = root.querySelector(`[data-bound="${bound}"]`)

            return [handle.getAttribute('aria-valuenow'), handle.getAttribute('aria-valuetext'), handle.textContent.trim()]
        },
        handle: (bound) => root.querySelector(`[data-bound="${bound}"]`),
        held: () => [root.querySelector('[data-meili="price-min"]').value, root.querySelector('[data-meili="price-max"]').value],
        readout: () => root.querySelector('[data-meili="price-readout"]').textContent,
    }
}

describe('a price range on the keyboard', () => {
    it('steps a handle and commits where it landed', () => {
        const price = control({ min: 55, max: 120 })

        press(price.window, price.handle('min'), 'ArrowRight')

        assert.deepEqual(price.held(), ['56', '120'])
        assert.deepEqual(price.committed, [[56, 120]])
    })

    it('goes to each end with Home and End', () => {
        const price = control({ min: 55, max: 120 })

        press(price.window, price.handle('min'), 'Home')
        assert.deepEqual(price.held(), ['0', '120'])

        press(price.window, price.handle('max'), 'End')
        assert.deepEqual(price.held(), ['0', '199'])
    })

    /**
     * Clamping each handle against the other pins both once they meet: at the top
     * the high one could no longer come back down, and at the bottom the low one
     * could not go up.
     */
    it('still moves when both ends sit on the same value', () => {
        const top = control({ min: 199, max: 199 })
        press(top.window, top.handle('max'), 'ArrowLeft')
        assert.deepEqual(top.held(), ['198', '199'])

        const bottom = control({ min: 0, max: 0 })
        press(bottom.window, bottom.handle('min'), 'ArrowRight')
        assert.deepEqual(bottom.held(), ['0', '1'])
    })

    it('never leaves what the catalogue can reach', () => {
        const price = control({ min: 0, max: 199 })

        press(price.window, price.handle('min'), 'ArrowLeft')
        press(price.window, price.handle('max'), 'ArrowRight')

        assert.deepEqual(price.held(), ['0', '199'])
    })

    it('writes what the shop writes, in the tip and in the readout', () => {
        const price = control({ min: 55, max: 120 })

        press(price.window, price.handle('min'), 'ArrowRight')

        assert.equal(price.handle('min').querySelector('[data-meili="price-tip"]').textContent, '56,00 €')
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
        press(price.window, price.handle('max'), 'ArrowLeft')

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
        press(price.window, price.handle('min'), 'Home')
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
        price.track().getBoundingClientRect = () => ({ left: 0, width: 199, top: 0, height: 10 })

        const at = (type, node, clientX) => node.dispatchEvent(new price.window.PointerEvent(type, { bubbles: true, pointerId: 1, clientX }))

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

        press(price.window, price.handle('max'), 'End')
        press(price.window, price.handle('min'), 'Home')

        assert.deepEqual(price.committed, [[55, null], [null, null]])
    })

    it('still commits a bound one step inside the edge', () => {
        const price = control({ min: 0, max: 199 })

        press(price.window, price.handle('max'), 'ArrowLeft')

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
        const committed = []
        new PriceControl(new Contract(root), description, (min, max) => committed.push([min, max])).start()

        const typed = (hook, value) => {
            const input = root.querySelector(`[data-meili="${hook}"]`)
            input.value = value
            input.dispatchEvent(new window.Event('change', { bubbles: true }))
        }
        typed('price-min', '9')
        typed('price-max', '30')

        assert.deepEqual(committed, [[null, null], [null, 30]])
    })
})
