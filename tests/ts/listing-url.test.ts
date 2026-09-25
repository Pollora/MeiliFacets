import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'

import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { ListingUrl } from '../../resources/assets/ts/listing/listing-url.ts'
import { described } from './fixtures.ts'

import type { StateDescription } from '../../resources/assets/ts/shared/description.ts'

const listing = described({
    facets: [{ taxonomy: 'product_brand', multiple: true, cap: 30, visible: 10, labels: {}, counts: {} }, { taxonomy: 'pa_size', multiple: true, cap: 30, visible: 10, labels: {}, counts: {} }],
    params: { product_brand: 'brand', pa_size: 'f_pa_size' },
    sorts: { price_asc: ['metas._price:asc'] },
})

const url = new ListingUrl(listing)

describe('ListingUrl', () => {
    // One state, one URL: an unsorted pair would be a second Varnish entry.
    it('sorts the values it writes', () => {
        assert.equal(
            url.toSearch(new ListingState({ facets: { product_brand: ['globex', 'acme'] }, page: 1 })),
            '?brand=acme,globex'
        )
    })

    // `page` is a public WordPress query var: the server never writes it either.
    it('paginates on pg, never on page', () => {
        assert.equal(url.toSearch(new ListingState({ facets: {}, page: 3 })), '?pg=3')
    })

    it('takes the parameter names the server declares', () => {
        const renamed = new ListingUrl(described({ ...listing, reserved: { ...listing.reserved, sort: 'tri', page: 'p2' } }))

        assert.equal(renamed.toSearch(new ListingState({ sort: 'price_asc', page: 2 })), '?tri=price_asc&p2=2')
    })

    it('writes only what differs from the default state', () => {
        assert.equal(url.toSearch(new ListingState({ facets: {}, page: 1 })), '')
        assert.equal(url.toSearch(new ListingState({ facets: { product_brand: [] }, page: 1 })), '')
    })

    it('writes nothing for a facet the server published no parameter for', () => {
        const unnamed = new ListingUrl(described({ ...listing, params: { product_brand: 'brand' } }))

        assert.equal(
            unnamed.toSearch(new ListingState({ facets: { product_brand: ['acme'], pa_size: ['large'] }, page: 1 })),
            '?brand=acme'
        )
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

    it('writes the path the server published, the page in pg', () => {
        const state = new ListingState({ facets: { product_brand: ['acme'] } })
        const published = new ListingUrl(described({ ...listing, pagePath: '/' }))

        assert.equal(url.toUrl(state.onPage(3)), '/shop?brand=acme&pg=3')
        assert.equal(published.toUrl(new ListingState()), '/')
    })

    it('writes the page query after its own parameters, as it was published', () => {
        const search = new ListingUrl(described({ ...listing, pageQuery: 's=cr%C3%A8me&post_type=product' }))

        assert.equal(search.toUrl(new ListingState({ sort: 'price_asc' })), '/shop?sort=price_asc&s=cr%C3%A8me&post_type=product')
        assert.equal(search.toUrl(new ListingState()), '/shop?s=cr%C3%A8me&post_type=product')
    })
})

interface WrittenUrl {
    case: string
    state: StateDescription
    search: string
}

/** Read back by `tests/Unit/StateReaderTest.php`: the state the client writes is the state the server reads. */
describe('a state written by the client', () => {
    const cases = JSON.parse(readFileSync(new URL('../url-writing-cases.json', import.meta.url), 'utf8')) as WrittenUrl[]
    const shared = new ListingUrl(described({
        facets: [
            { taxonomy: 'product_brand', multiple: true, cap: 30, visible: 10, labels: {}, counts: {} },
            { taxonomy: 'product_cat', multiple: false, cap: 1, visible: 10, labels: {}, counts: {} },
        ],
        params: { product_brand: 'brand', product_cat: 'f_product_cat' },
        sorts: { price_asc: ['metas._price:asc'] },
        priceFields: { min: 'price.min', max: 'price.max' },
    }))

    for (const expected of cases) {
        it(expected.case, () => {
            const state = new ListingState(expected.state)

            assert.deepEqual(state.toDescription(), expected.state, 'state')
            assert.equal(shared.toSearch(state), expected.search, 'search')
        })
    }
})
