import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { PriceQuery } from '../../resources/assets/ts/price/price-query.ts'
import { RESULTS } from '../../resources/assets/ts/shared/plan.ts'
import { described } from './fixtures.ts'

import type { Range } from '../../resources/assets/ts/shared/range.ts'

const query = new PriceQuery({ min: 'price.min', max: 'price.max' })

const stats = (min: number, max: number) => ({
    facetStats: { 'price.min': { min, max: 190 }, 'price.max': { min: 12, max } },
})

const span = (range: Range) => ({ min: range.min, max: range.max })

describe('price bounds read off the answers', () => {
    it('reads the search that measured them apart before the main one', () => {
        const answers = { [RESULTS]: stats(0, 199), [PriceQuery.KEY]: stats(9, 47) }

        assert.deepEqual(span(query.boundsFrom(answers)), { min: 9, max: 47 })
    })

    it('falls back on the main search while no range is held', () => {
        assert.deepEqual(span(query.boundsFrom({ [RESULTS]: stats(0, 199) })), { min: 0, max: 199 })
    })

    it('widens them to whole units, as the server does', () => {
        assert.deepEqual(span(query.boundsFrom({ [RESULTS]: stats(9.8, 46.4) })), { min: 9, max: 47 })
    })

    it('reads none over a single price, and a span over a price with cents', () => {
        assert.equal(query.boundsFrom({ [RESULTS]: stats(20, 20) }).isEmpty(), true)
        assert.deepEqual(span(query.boundsFrom({ [RESULTS]: stats(20, 20.4) })), { min: 20, max: 21 })
    })

    it('reads none when the engine measured nothing', () => {
        assert.equal(query.boundsFrom({ [RESULTS]: { hits: [] } }).isEmpty(), true)
    })

    it('reads none from a single bound', () => {
        const answers = { [RESULTS]: { facetStats: { 'price.min': { min: 9, max: 40 } } } }

        assert.equal(query.boundsFrom(answers).isEmpty(), true)
    })

    it('is not planned for a listing that declares no range', () => {
        const queries = filterQueriesOf(described({ facets: [], priceFields: null }))

        assert.equal(queries.some((filterQuery) => filterQuery instanceof PriceQuery), false)
    })
})
