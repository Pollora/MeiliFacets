import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { ListingQuery } from '../../resources/assets/js/listing-query.js'
import { ListingState } from '../../resources/assets/js/listing-state.js'
import { ListingUrl } from '../../resources/assets/js/listing-url.js'

const description = {
    name: 'products',
    facets: [{ taxonomy: 'product_brand', multiple: true, cap: 30, visible: 10 }],
    params: { product_brand: 'marque' },
    reserved: { sort: 'sort', query: 'q', page: 'pg', minPrice: 'min_price', maxPrice: 'max_price' },
    sorts: { newest: ['post_date:desc'] },
    priceFields: { min: 'price.min', max: 'price.max' },
    filter: 'post_type = "product"',
    perPage: 16,
    attributes: ['card'],
    reachableHits: 1000,
}

describe('a price range in the client state', () => {
    it('refuses what is not a price', () => {
        const state = new ListingState({ price: { min: 'trente', max: -5 } })

        assert.deepEqual(state.price, { min: null, max: null })
    })

    it('reads a bound written as text, as a URL gives it', () => {
        assert.deepEqual(new ListingState({ price: { min: '40', max: '70.5' } }).price, { min: 40, max: 70.5 })
    })

    it('is not pristine once a single bound is held', () => {
        assert.equal(new ListingState().isPristine(), true)
        assert.equal(new ListingState({ price: { min: 0 } }).isPristine(), false)
    })

    it('goes back to the first page when the range moves', () => {
        assert.equal(new ListingState({ page: 4 }).pricedBetween(40, 70).page, 1)
    })

    it('survives a change to something else', () => {
        const state = new ListingState({ price: { min: 40, max: 70 } }).searching('crème')

        assert.deepEqual(state.price, { min: 40, max: 70 })
    })
})

describe('a price range in the URL', () => {
    const url = new ListingUrl(description)

    it('writes only the bounds that were asked for', () => {
        assert.equal(url.toSearch(new ListingState({ price: { min: 40, max: 70 } })), '?min_price=40&max_price=70')
        assert.equal(url.toSearch(new ListingState({ price: { min: 40 } })), '?min_price=40')
        assert.equal(url.toSearch(new ListingState()), '')
    })

    it('reads back what it wrote', () => {
        const state = url.toState('?min_price=40&max_price=70')

        assert.deepEqual(state.price, { min: 40, max: 70 })
    })

    /** Zero is a bound a shop can hold: a free product must stay reachable. */
    it('reads no range for a listing that declares none', () => {
        const without = new ListingUrl({ ...description, priceFields: null })

        assert.deepEqual(without.toState('?min_price=20&max_price=60').price, { min: null, max: null })
    })

    it('keeps a bound of zero', () => {
        assert.equal(url.toSearch(new ListingState({ price: { min: 0 } })), '?min_price=0')
        assert.deepEqual(url.toState('?min_price=0').price.min, 0)
    })
})

describe('a price range in the search', () => {
    const query = new ListingQuery(description)

    it('asks the engine for an overlap, as the server does', () => {
        const { filter } = query.plan(new ListingState({ price: { min: 40, max: 70 } })).results

        assert.equal(filter, 'post_type = "product" AND price.min <= 70 AND price.max >= 40')
    })

    it('leaves an open end unconstrained', () => {
        const { filter } = query.plan(new ListingState({ price: { min: 40 } })).results

        assert.equal(filter, 'post_type = "product" AND price.max >= 40')
    })

    it('asks the main search for the bounds while no range is held', () => {
        const plan = query.plan(new ListingState())

        assert.deepEqual(plan.results.facets, ['facets.product_brand', 'price.min', 'price.max'])
        assert.equal(plan[ListingQuery.BOUNDS], undefined)
    })

    it('measures the bounds apart, with the range lifted, once one is held', () => {
        const plan = query.plan(new ListingState({ facets: { product_brand: ['aeris'] }, price: { min: 40 } }))

        assert.deepEqual(plan.results.facets, [])
        assert.deepEqual(plan[ListingQuery.BOUNDS], {
            q: '',
            filter: 'post_type = "product" AND facets.product_brand = "aeris"',
            facets: ['price.min', 'price.max'],
            hitsPerPage: 0,
            page: 1,
        })
    })

    it('asks for no bounds when no listing declares a range', () => {
        const without = new ListingQuery({ ...description, priceFields: null })
        const plan = without.plan(new ListingState({ price: { min: 40 } }))

        assert.deepEqual(plan.results.facets, ['facets.product_brand'])
        assert.equal(plan[ListingQuery.BOUNDS], undefined)
    })

    it('writes no clause when no listing declares a range', () => {
        const without = new ListingQuery({ ...description, priceFields: null })
        const { filter } = without.plan(new ListingState({ price: { min: 40, max: 70 } })).results

        assert.equal(filter, 'post_type = "product"')
    })
})
