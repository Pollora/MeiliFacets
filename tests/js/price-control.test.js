import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/js/contract.js'
import { PriceControl } from '../../resources/assets/js/price-control.js'
import { CONTRACT, open, press } from './dom.js'

const description = { money: { format: '%2$s %1$s', symbol: '€', decimals: 2, decimal: ',', thousand: ' ' } }

const markup = ({ min, max, reachable = { min: 0, max: 199 } }) => `
<div data-listing data-meili-contract="${CONTRACT}">
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
  <input data-meili="price-min" name="min_price" value="${min}">
  <input data-meili="price-max" name="max_price" value="${max}">
</div>`

const control = (state) => {
    const { window, root } = open(markup(state))
    const committed = []

    new PriceControl(new Contract(root), description, (min, max) => committed.push([min, max])).start()

    return {
        window,
        committed,
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
