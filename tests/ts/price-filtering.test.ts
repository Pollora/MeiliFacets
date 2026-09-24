import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingQuery } from '../../resources/assets/ts/listing/listing-query.ts'
import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { ListingUrl } from '../../resources/assets/ts/listing/listing-url.ts'
import { PriceQuery } from '../../resources/assets/ts/price/price-query.ts'
import { described } from './fixtures.ts'

const description = described({
    name: 'products',
    facets: [{ taxonomy: 'product_brand', multiple: true, cap: 30, visible: 10, labels: {}, counts: {} }],
    params: { product_brand: 'marque' },
    reserved: { sort: 'sort', query: 'q', page: 'pg', minPrice: 'min_price', maxPrice: 'max_price' },
    sorts: { newest: ['post_date:desc'] },
    priceFields: { min: 'price.min', max: 'price.max' },
    filter: 'post_type = "product"',
    perPage: 16,
    attributes: ['card'],
    reachableHits: 1000,
})

describe('a price range in the client state', () => {
    it('refuses what is not a price', () => {
        assert.equal(new ListingState({ price: { min: Number.NaN, max: -5 } }).price.isEmpty(), true)
        assert.equal(new ListingState({ price: { max: Number.POSITIVE_INFINITY } }).price.isEmpty(), true)
    })

    /** `>= -0` is not read as `>= 0` by the engine: a free product would be lost. */
    it('holds a negative zero as zero', () => {
        assert.equal(Object.is(new ListingState({ price: { min: -0 } }).price.min, 0), true)
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

        assert.deepEqual([state.price.min, state.price.max], [40, 70])
    })
})

describe('a price range in the URL', () => {
    const url = new ListingUrl(description)

    it('writes only the bounds that were asked for', () => {
        assert.equal(url.toSearch(new ListingState({ price: { min: 40, max: 70 } })), '?min_price=40&max_price=70')
        assert.equal(url.toSearch(new ListingState({ price: { min: 40 } })), '?min_price=40')
        assert.equal(url.toSearch(new ListingState()), '')
    })

    it('writes a bound as the filter does, never in exponent notation', () => {
        const url = new ListingUrl(description)

        assert.equal(url.toSearch(new ListingState({ price: { min: 0.0000001, max: 12.5 } })), '?min_price=0&max_price=12.5')
    })

    /** Zero is a bound a shop can hold: a free product must stay reachable. */
    it('keeps a bound of zero', () => {
        assert.equal(url.toSearch(new ListingState({ price: { min: 0 } })), '?min_price=0')
    })
})

describe('a price range in the search', () => {
    const query = new ListingQuery(description, filterQueriesOf(description))

    it('asks the engine for an overlap, as the server does', () => {
        const { filter } = query.plan(new ListingState({ price: { min: 40, max: 70 } })).results

        assert.equal(filter, 'post_type = "product" AND price.min <= 70 AND price.max >= 40')
    })

    it('writes a bound as the server writes it, never in exponent notation', () => {
        const { filter } = query.plan(new ListingState({ price: { min: 0.0000001, max: 1e21 } })).results

        assert.equal(filter, 'post_type = "product" AND price.min <= 1000000000000000000000 AND price.max >= 0')
    })

    it('leaves an open end unconstrained', () => {
        const { filter } = query.plan(new ListingState({ price: { min: 40 } })).results

        assert.equal(filter, 'post_type = "product" AND price.max >= 40')
    })

    it('asks the main search for the bounds while no range is held', () => {
        const plan = query.plan(new ListingState())

        assert.deepEqual(plan.results.facets, ['facets.product_brand', 'price.min', 'price.max'])
        assert.equal(plan[PriceQuery.KEY], undefined)
    })

    it('measures the bounds apart, with the range lifted, once one is held', () => {
        const plan = query.plan(new ListingState({ facets: { product_brand: ['aeris'] }, price: { min: 40 } }))

        assert.deepEqual(plan.results.facets, [])
        assert.deepEqual(plan[PriceQuery.KEY], {
            q: '',
            filter: 'post_type = "product" AND facets.product_brand = "aeris"',
            facets: ['price.min', 'price.max'],
            hitsPerPage: 0,
            page: 1,
        })
    })

    it('asks for no bounds when no listing declares a range', () => {
        const withoutPrice = described({ ...description, priceFields: null })
        const without = new ListingQuery(withoutPrice, filterQueriesOf(withoutPrice))
        const plan = without.plan(new ListingState({ price: { min: 40 } }))

        assert.deepEqual(plan.results.facets, ['facets.product_brand'])
        assert.equal(plan[PriceQuery.KEY], undefined)
    })

    it('writes no clause when no listing declares a range', () => {
        const withoutPrice = described({ ...description, priceFields: null })
        const without = new ListingQuery(withoutPrice, filterQueriesOf(withoutPrice))
        const { filter } = without.plan(new ListingState({ price: { min: 40, max: 70 } })).results

        assert.equal(filter, 'post_type = "product"')
    })

    it('keeps the held range on the search that counts a facet apart', () => {
        const counting = query.plan(new ListingState({ facets: { product_brand: ['aeris'] }, price: { max: 70 } }))['count:product_brand']

        assert.equal(counting?.filter, 'post_type = "product" AND price.min <= 70')
    })
})
