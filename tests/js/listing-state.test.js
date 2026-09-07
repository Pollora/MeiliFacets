import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { ListingState } from '../../resources/assets/js/listing-state.js'

/**
 * What `StateReader` does on the server, done again here. The two are compared
 * on their constants by `ContractParityTest`; these cases compare the treatment,
 * which is where they drifted apart.
 */
describe('ListingState, against StateReader', () => {
    it('trims a query before bounding it', () => {
        assert.equal(new ListingState({ query: '  chaise  ' }).query, 'chaise')
    })

    /** Cutting UTF-16 units would leave half a surrogate pair, which is not a string. */
    it('bounds the query by code point', () => {
        const query = new ListingState({ query: 'a'.repeat(150) + '😀'.repeat(100) }).query

        assert.equal([...query].length, 200)
        assert.equal([...query].at(-1), '😀')
    })

    /** A slug may be the string "0" — a size, a year. Dropping it is dropping a facet value. */
    it('keeps a value that reads as false', () => {
        assert.deepEqual(ListingState.valuesFrom('0,a'), ['0', 'a'])
    })

    it('drops the empty and the blank', () => {
        assert.deepEqual(ListingState.valuesFrom('a,,  ,b'), ['a', 'b'])
    })

    /** One state must have one URL, or a cache holds it twice. */
    it('sorts and deduplicates', () => {
        assert.deepEqual(ListingState.valuesFrom('b,a,b'), ['a', 'b'])
    })

    it('counts only ticked values as filters', () => {
        const state = new ListingState({ facets: { brand: ['a', 'b'], cat: ['x'] }, sort: 'price', page: 3 })

        assert.equal(state.activeFilterCount(), 3)
        assert.equal(state.isPristine(), false)
        assert.equal(new ListingState().isPristine(), true)
    })
})
