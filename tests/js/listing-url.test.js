import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { ListingState } from '../../resources/assets/js/listing-state.js'
import { ListingUrl } from '../../resources/assets/js/listing-url.js'

const listing = {
    facets: [{ taxonomy: 'product_brand', multiple: true, cap: 30 }, { taxonomy: 'pa_size', multiple: true, cap: 30 }],
    params: { product_brand: 'brand', pa_size: 'f_pa_size' },
    reserved: { sort: 'sort', query: 'q', page: 'pg' },
    sorts: { price_asc: ['metas._price:asc'] },
}

const url = new ListingUrl(listing)

describe('ListingUrl', () => {
    /** Mirrors StateReader: the control would otherwise announce an order nobody applied. */
    it('drops a sort the listing does not declare', () => {
        assert.equal(url.toState('?sort=price_asc').sort, 'price_asc')
        assert.equal(url.toState('?sort=forged').sort, null)
    })

    it('reads a mapped parameter under its configured name', () => {
        assert.deepEqual(url.toState('?brand=acme').facets, { product_brand: ['acme'] })
    })

    /** A bare taxonomy name is a public WordPress query var: only the declared name is read. */
    it('never reads a facet under its bare taxonomy name', () => {
        assert.deepEqual(url.toState('?pa_size=large').facets, {})
    })

    /** Mirrors StateReader::values: a crafted URL must not turn into thousands of clauses. */
    it('caps what a crafted URL may carry', () => {
        const capped = new ListingUrl({
            ...listing,
            facets: [{ taxonomy: 'product_brand', multiple: true, cap: 3 }, { taxonomy: 'pa_size', multiple: false, cap: 1 }],
        })

        assert.deepEqual(capped.toState('?brand=a,b,c,d,e,f').facets.product_brand, ['a', 'b', 'c'])
        assert.deepEqual(capped.toState('?f_pa_size=x,y,z').facets.pa_size, ['x'])
    })

    it('splits several values of one facet', () => {
        assert.deepEqual(url.toState('?brand=acme,globex').facets.product_brand, ['acme', 'globex'])
    })

    // One state, one URL: an unsorted pair would be a second Varnish entry.
    it('sorts values when reading and when writing', () => {
        assert.deepEqual(url.toState('?brand=globex,acme').facets.product_brand, ['acme', 'globex'])
        assert.equal(
            url.toSearch(new ListingState({ facets: { product_brand: ['globex', 'acme'] }, page: 1 })),
            '?brand=acme,globex'
        )
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
        assert.equal(url.toState('?pg=abc').page, 1)
        assert.equal(url.toState('?pg=0').page, 1)
    })

    // `page` is a public WordPress query var: the server never writes it either.
    it('paginates on pg, never on page', () => {
        assert.equal(url.toState('?pg=3').page, 3)
        assert.equal(url.toState('?page=3').page, 1)
        assert.equal(url.toSearch(new ListingState({ facets: {}, page: 3 })), '?pg=3')
    })

    // One state, one URL: an array form would give Varnish a second cache entry.
    it('reads the comma form only', () => {
        assert.deepEqual(url.toState('?brand=acme,globex').facets.product_brand, ['acme', 'globex'])
        assert.deepEqual(url.toState('?brand[]=acme&brand[]=globex').facets, {})
    })

    it('takes the parameter names the server declares', () => {
        const renamed = new ListingUrl({ ...listing, reserved: { sort: 'tri', query: 'q', page: 'p2' } })

        assert.equal(renamed.toState('?p2=2').page, 2)
        assert.equal(renamed.toSearch(new ListingState({ sort: 'price_asc' })), '?tri=price_asc')
    })

    it('writes only what differs from the default state', () => {
        assert.equal(url.toSearch(new ListingState({ facets: {}, page: 1 })), '')
        assert.equal(url.toSearch(new ListingState({ facets: { product_brand: [] }, page: 1 })), '')
    })

    it('writes each facet under its parameter, commas left readable', () => {
        const search = url.toSearch(new ListingState({
            facets: { product_brand: ['acme', 'globex'], pa_size: ['large'] },
            page: 1,
        }))

        assert.equal(search, '?brand=acme,globex&f_pa_size=large')
    })

    it('writes a page only past the first', () => {
        assert.equal(url.toSearch(new ListingState({ facets: {}, page: 2 })), '?pg=2')
        assert.equal(url.toSearch(new ListingState({ facets: {}, page: 1 })), '')
    })

    it('writes the query and the sort when they are set', () => {
        assert.equal(url.toSearch(new ListingState({ facets: {}, query: 'coat', sort: 'price_asc', page: 1 })),
            '?q=coat&sort=price_asc')
    })

    it('reads back a state it has written', () => {
        const state = new ListingState({
            facets: { product_brand: ['acme', 'globex'], pa_size: ['large'] },
            query: 'coat',
            sort: 'price_asc',
            page: 3,
        })

        const read = url.toState(url.toSearch(state))

        assert.deepEqual(read.facets, state.facets)
        assert.equal(read.query, state.query)
        assert.equal(read.sort, state.sort)
        assert.equal(read.page, state.page)
    })
})
