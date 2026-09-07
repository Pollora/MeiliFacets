import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { ListingQuery } from '../../resources/assets/js/listing-query.js'
import { ListingState } from '../../resources/assets/js/listing-state.js'

const listing = {
    perPage: 16,
    facets: [
        { taxonomy: 'product_brand', multiple: true },
        { taxonomy: 'pa_size', multiple: true },
    ],
    filter: 'post_type = product',
    sorts: { price_asc: ['metas._price:asc'] },
}

const plan = (state, overrides = {}) => new ListingQuery({ ...listing, ...overrides }).plan(new ListingState(state))
const build = (state, overrides = {}) => plan(state, overrides)[ListingQuery.RESULTS]

describe('ListingQuery', () => {
    it('asks for the whole listing when nothing is selected', () => {
        const request = build({})

        assert.equal(request.filter, 'post_type = product')
        assert.equal(request.hitsPerPage, 16)
        assert.equal(request.page, 1)
        assert.deepEqual(request.facets, ['facets.product_brand', 'facets.pa_size'])
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

    it('asks for a page and clamps one below the first', () => {
        assert.equal(build({ page: 3 }).page, 3)
        assert.equal(build({ page: 0 }).page, 1)
        assert.equal(build({ page: -5 }).page, 1)
    })

    // The server keys its searches the same way: results, then count:<taxonomy>.
    it('plans one extra search per constrained multi-select facet', () => {
        assert.deepEqual(Object.keys(plan({})), ['results'])
        assert.deepEqual(
            Object.keys(plan({ facets: { product_brand: ['acme'] } })),
            ['results', 'count:product_brand']
        )
    })

    it('never counts a facet twice', () => {
        const state = { facets: { product_brand: ['acme'] } }
        const queries = plan(state)

        assert.ok(!queries.results.facets.includes('facets.product_brand'))
        assert.deepEqual(queries['count:product_brand'].facets, ['facets.product_brand'])
    })

    it('lifts only the counted facet from its own filter', () => {
        const state = { facets: { product_brand: ['acme'], pa_size: ['large'] } }
        const counting = plan(state)['count:product_brand']

        assert.ok(!counting.filter.includes('product_brand'))
        assert.ok(counting.filter.includes('facets.pa_size = "large"'))
        assert.equal(counting.hitsPerPage, 0)
    })

    it('leaves a single-select facet on the main search', () => {
        const single = { facets: [{ taxonomy: 'product_cat', multiple: false }] }
        const state = { facets: { product_cat: ['coats'] } }

        assert.deepEqual(Object.keys(plan(state, single)), ['results'])
        assert.deepEqual(plan(state, single).results.facets, ['facets.product_cat'])
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
