import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Range } from '../../resources/assets/ts/shared/range.ts'

describe('Range', () => {
    it('places a price between its bounds to four decimals, as the server writes it', () => {
        assert.equal(new Range(0, 199).ratio(55), 0.2764)
    })

    it('places nothing past its bounds, and everything at the start without a span', () => {
        assert.equal(new Range(0, 199).ratio(250), 1)
        assert.equal(new Range(20, 20).ratio(20), 0)
        assert.equal(new Range().ratio(55), 0)
        assert.equal(new Range(0, 199).ratio(null), 0)
    })

    it('clamps against the bounds it has, and leaves an open one open', () => {
        assert.equal(new Range(10, null).clamp(4), 10)
        assert.equal(new Range(null, 50).clamp(4), 4)
    })
})
