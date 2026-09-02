import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { ListingQuery } from '../../resources/assets/js/listing-query.js'

const listing = {
    perPage: 16,
    facets: ['facets.product_brand', 'facets.pa_size'],
    filter: 'post_type = product',
    sorts: { price_asc: ['metas._price:asc'] },
}

const build = (state, overrides = {}) => new ListingQuery({ ...listing, ...overrides }).build(state)

describe('ListingQuery', () => {
    it('asks for the whole listing when nothing is selected', () => {
        const request = build({})

        assert.equal(request.filter, 'post_type = product')
        assert.equal(request.limit, 16)
        assert.equal(request.offset, 0)
        assert.deepEqual(request.facets, listing.facets)
    })

    it('joins the values of one facet with OR', () => {
        const { filter } = build({ facets: { product_brand: ['acme', 'globex'] } })

        assert.equal(
            filter,
            'post_type = product AND (facets.product_brand = "acme" OR facets.product_brand = "globex")'
        )
    })

    it('joins two facets with AND, and leaves a single value unwrapped', () => {
        const { filter } = build({
            facets: { product_brand: ['acme'], pa_size: ['large'] },
        })

        assert.equal(
            filter,
            'post_type = product AND facets.product_brand = "acme" AND facets.pa_size = "large"'
        )
    })

    it('ignores a facet left empty', () => {
        const { filter } = build({ facets: { product_brand: [] } })

        assert.equal(filter, 'post_type = product')
    })

    it('omits the base clause when the listing declares none', () => {
        const { filter } = build({ facets: { product_brand: ['acme'] } }, { filter: null })

        assert.equal(filter, 'facets.product_brand = "acme"')
    })

    it('escapes a quote so it cannot close the value', () => {
        const { filter } = build({ facets: { product_brand: ['5" band'] } }, { filter: null })

        assert.equal(filter, 'facets.product_brand = "5\\" band"')
    })

    // A value ending in a backslash used to close its own string, letting the
    // rest of the URL through as filter syntax.
    it('escapes a trailing backslash rather than letting it escape the closing quote', () => {
        const { filter } = build({ facets: { product_brand: ['back\\'] } }, { filter: null })

        assert.equal(filter, 'facets.product_brand = "back\\\\"')
    })

    it('does not let an injected clause out of the value', () => {
        const { filter } = build(
            { facets: { product_brand: ['x" OR post_status = "draft'] } },
            { filter: null }
        )

        assert.equal(filter, 'facets.product_brand = "x\\" OR post_status = \\"draft"')
    })

    it('offsets by whole pages and clamps a page below the first', () => {
        assert.equal(build({ page: 3 }).offset, 32)
        assert.equal(build({ page: 0 }).offset, 0)
        assert.equal(build({ page: -5 }).offset, 0)
    })

    it('sorts only by a sort the listing declares', () => {
        assert.deepEqual(build({ sort: 'price_asc' }).sort, ['metas._price:asc'])
        assert.equal(build({ sort: 'unknown' }).sort, undefined)
        assert.equal(build({}).sort, undefined)
    })

    it('restricts the retrieved attributes when the listing names them', () => {
        assert.deepEqual(build({}, { attributes: ['card'] }).attributesToRetrieve, ['card'])
        assert.equal(build({}).attributesToRetrieve, undefined)
    })
})
