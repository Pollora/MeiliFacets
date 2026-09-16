import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { ListingQuery } from '../../resources/assets/js/listing-query.js'
import { PriceBounds } from '../../resources/assets/js/price-bounds.js'

const fields = { min: 'price.min', max: 'price.max' }

const stats = (min, max) => ({
    facetStats: { 'price.min': { min, max: 190 }, 'price.max': { min: 12, max } },
})

describe('price bounds read off the answers', () => {
    it('reads the search that measured them apart before the main one', () => {
        const answers = { [ListingQuery.RESULTS]: stats(0, 199), [ListingQuery.BOUNDS]: stats(9, 47) }

        assert.deepEqual(new PriceBounds(answers).of(fields), { min: 9, max: 47 })
    })

    it('falls back on the main search while no range is held', () => {
        assert.deepEqual(new PriceBounds({ [ListingQuery.RESULTS]: stats(0, 199) }).of(fields), { min: 0, max: 199 })
    })

    it('widens them to whole units, as the server does', () => {
        assert.deepEqual(new PriceBounds({ [ListingQuery.RESULTS]: stats(9.8, 46.4) }).of(fields), { min: 9, max: 47 })
    })

    it('reads none over a single price, and a span over a price with cents', () => {
        assert.equal(new PriceBounds({ [ListingQuery.RESULTS]: stats(20, 20) }).of(fields), null)
        assert.deepEqual(new PriceBounds({ [ListingQuery.RESULTS]: stats(20, 20.4) }).of(fields), { min: 20, max: 21 })
    })

    it('reads none when the engine measured nothing', () => {
        assert.equal(new PriceBounds({ [ListingQuery.RESULTS]: { hits: [] } }).of(fields), null)
    })

    it('reads none from a single end', () => {
        const answers = { [ListingQuery.RESULTS]: { facetStats: { 'price.min': { min: 9, max: 40 } } } }

        assert.equal(new PriceBounds(answers).of(fields), null)
    })

    it('reads none for a listing that declares no range', () => {
        assert.equal(new PriceBounds({ [ListingQuery.RESULTS]: stats(0, 199) }).of(null), null)
    })
})
