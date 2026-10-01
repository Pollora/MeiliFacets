import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'

describe('ListingState', () => {
    /** Cutting UTF-16 units would leave half a surrogate pair, which is not a string. */
    it('bounds the query by code point', () => {
        const query = new ListingState({ query: 'a'.repeat(150) + '😀'.repeat(100) }).query

        assert.equal([...query].length, 200)
        assert.equal([...query].at(-1), '😀')
    })

    /** R-159: the engine tokenises such a term into nothing and serves the whole index. */
    it('holds no term for one with no letter and no figure, served or searched', () => {
        assert.equal(new ListingState({ query: '?(' }).query, '')
        assert.equal(new ListingState().searching(' -- ').query, '')
        assert.equal(new ListingState().searching(' é ').query, 'é')
        assert.equal(new ListingState().searching('4').query, '4')
    })

    /** A slug may be the string "0" — a size, a year. Dropping it is dropping a facet value. */
    it('keeps a value that reads as false', () => {
        assert.deepEqual(new ListingState({ facets: { size: ['0', 'a'] } }).selected('size'), ['0', 'a'])
    })

    /** One state must have one URL, or a cache holds it twice. */
    it('sorts and deduplicates', () => {
        assert.deepEqual(new ListingState({ facets: { brand: ['b', 'a', 'b'] } }).selected('brand'), ['a', 'b'])
    })

    it('gives back the state it was built from', () => {
        const state = new ListingState({ facets: { brand: ['a'] }, query: 'coat', sort: 'newest', page: 2, price: { min: 10 } })

        assert.deepEqual(new ListingState(state.toDescription()).toDescription(), {
            facets: { brand: ['a'] },
            query: 'coat',
            sort: 'newest',
            page: 2,
            price: { min: 10, max: null },
        })
    })

    it('counts ticked values as filters, never the sort or the page', () => {
        const state = new ListingState({ facets: { brand: ['a', 'b'], cat: ['x'] }, sort: 'price', page: 3 })

        assert.equal(state.activeFilterCount(), 3)
        assert.equal(state.isPristine(), false)
        assert.equal(new ListingState().isPristine(), true)
    })

    it('counts a price range as one filter, whatever its ends', () => {
        assert.equal(new ListingState({ price: { min: 20, max: 60 } }).activeFilterCount(), 1)
        assert.equal(new ListingState({ price: { max: 60 } }).activeFilterCount(), 1)
        assert.equal(new ListingState({ facets: { brand: ['a', 'b'] }, price: { min: 20 } }).activeFilterCount(), 3)
    })
})
