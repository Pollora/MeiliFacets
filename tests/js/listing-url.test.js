import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { ListingUrl } from '../../resources/assets/js/listing-url.js'

const listing = {
    facets: ['facets.product_brand', 'facets.pa_size'],
    params: { product_brand: 'brand' },
}

const url = new ListingUrl(listing)

describe('ListingUrl', () => {
    it('reads a mapped parameter under its configured name', () => {
        assert.deepEqual(url.toState('?brand=acme').facets, { product_brand: ['acme'] })
    })

    it('prefixes a taxonomy left unmapped, never using its bare name', () => {
        assert.deepEqual(url.toState('?f_pa_size=large').facets, { pa_size: ['large'] })
        assert.deepEqual(url.toState('?pa_size=large').facets, {})
    })

    it('splits several values of one facet', () => {
        assert.deepEqual(url.toState('?brand=acme,globex').facets.product_brand, ['acme', 'globex'])
    })

    it('drops the empty segments of a trailing separator', () => {
        assert.deepEqual(url.toState('?brand=acme,,').facets.product_brand, ['acme'])
    })

    it('ignores a parameter the listing does not declare', () => {
        assert.deepEqual(url.toState('?couleur=rouge').facets, {})
    })

    it('defaults to the first page and no sort', () => {
        const state = url.toState('')

        assert.equal(state.page, 1)
        assert.equal(state.sort, null)
        assert.equal(state.query, '')
    })

    it('falls back to the first page on an unreadable page number', () => {
        assert.equal(url.toState('?page=abc').page, 1)
        assert.equal(url.toState('?page=0').page, 1)
    })

    it('writes only what differs from the default state', () => {
        assert.equal(url.toSearch({ facets: {}, page: 1 }), '')
        assert.equal(url.toSearch({ facets: { product_brand: [] }, page: 1 }), '')
    })

    it('writes each facet under its parameter, commas left readable', () => {
        const search = url.toSearch({
            facets: { product_brand: ['acme', 'globex'], pa_size: ['large'] },
            page: 1,
        })

        assert.equal(search, '?brand=acme,globex&f_pa_size=large')
    })

    it('writes a page only past the first', () => {
        assert.equal(url.toSearch({ facets: {}, page: 2 }), '?page=2')
        assert.equal(url.toSearch({ facets: {}, page: 1 }), '')
    })

    it('writes the query and the sort when they are set', () => {
        assert.equal(url.toSearch({ facets: {}, query: 'coat', sort: 'price_asc', page: 1 }),
            '?q=coat&sort=price_asc')
    })

    it('reads back a state it has written', () => {
        const state = {
            facets: { product_brand: ['acme', 'globex'], pa_size: ['large'] },
            query: 'coat',
            sort: 'price_asc',
            page: 3,
        }

        assert.deepEqual(url.toState(url.toSearch(state)), state)
    })
})
