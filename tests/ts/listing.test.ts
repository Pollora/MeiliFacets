import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { SearchError, SearchSuperseded } from '../../resources/assets/ts/shared/search-client.ts'
import { connection, described, FakeClient, FakeHistory, served } from './fixtures.ts'

import type { StateChanges } from '../../resources/assets/ts/listing/listing-state.ts'
import type { FailedDetail, ResultsDetail } from '../../resources/assets/ts/listing/listing.ts'
import type { ListingDescription } from '../../resources/assets/ts/shared/description.ts'

const description = described({
    name: 'products',
    perPage: 16,
    filter: 'post_type = "product"',
    apply: 'submit',
    attributes: ['card'],
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 3, visible: 10, counts: {} },
        { taxonomy: 'product_cat', multiple: false, cap: 1, visible: 10, counts: {} },
    ],
    params: { product_brand: 'brand' },
    reserved: { sort: 'sort', query: 'q', page: 'pg', minPrice: 'min_price', maxPrice: 'max_price' },
    sorts: { price_asc: ['metas._price:asc'] },
})

const build = (state: StateChanges = {}, overrides: Partial<ListingDescription> = {}, failure: Error | null = null) => {
    const history = new FakeHistory()
    const client = new FakeClient()
    const listed = described({ ...description, state: served(state), ...overrides })
    const listing = new Listing(listed, connection, {
        filterQueries: filterQueriesOf(listed),
        client,
        history,
    })
    const heard: [string, unknown][] = []

    client.failure = failure

    for (const name of ['change', 'results', 'failed']) {
        listing.addEventListener(name, (event) => heard.push([name, (event as CustomEvent).detail]))
    }

    return { listing, history, client, heard }
}

describe('Listing', () => {
    it('writes the path the server published', async () => {
        const { listing, history } = build({}, { pagePath: '/boutique' })

        await listing.toggle('product_brand', 'acme').apply()

        assert.deepEqual(history.replaced, ['/boutique?brand=acme'])
    })

    it('writes the page query after its own parameters', async () => {
        const { listing, history } = build({}, { pageQuery: 's=&post_type=product' })

        await listing.toggle('product_brand', 'acme').apply()

        assert.deepEqual(history.replaced, ['/shop?brand=acme&s=&post_type=product'])
    })

    it('starts from the state the server read', () => {
        const history = new FakeHistory()
        const read = described({ ...description, state: served({ facets: { product_brand: ['acme'] }, page: 2 }) })
        const listing = new Listing(read, connection, { filterQueries: filterQueriesOf(read), client: new FakeClient(), history })

        assert.deepEqual(listing.state.selected('product_brand'), ['acme'])
        assert.equal(listing.state.page, 2)
    })

    it('trims a query typed with spaces around it', () => {
        const { listing } = build()

        assert.equal(listing.search('  coat ').state.query, 'coat')
    })

    it('ignores a blank value, and trims one written with spaces', () => {
        const { listing } = build()

        assert.deepEqual(listing.toggle('product_brand', '  ').state.facets, {})
        assert.deepEqual(listing.toggle('product_brand', ' acme ').state.selected('product_brand'), ['acme'])
    })

    it('adds a value, then removes it', () => {
        const { listing } = build()

        assert.deepEqual(listing.toggle('product_brand', 'acme').state.selected('product_brand'), ['acme'])
        assert.deepEqual(listing.toggle('product_brand', 'acme').state.selected('product_brand'), [])
    })

    /** A radio group holds one value: ticking a second must not keep the first. */
    it('replaces the value of a facet that holds one at a time', () => {
        const { listing } = build()

        listing.toggle('product_cat', 'coats')

        assert.deepEqual(listing.toggle('product_cat', 'hats').state.selected('product_cat'), ['hats'])
    })

    /** A crafted URL must not turn into an unbounded list of filter clauses. */
    it('never holds more values than the facet allows', () => {
        const { listing } = build()

        for (const brand of ['a', 'b', 'c', 'd']) {
            listing.toggle('product_brand', brand)
        }

        assert.equal(listing.state.selected('product_brand').length, 3)
    })

    it('ignores a taxonomy the listing does not declare', () => {
        const { listing } = build()

        assert.deepEqual(listing.toggle('unknown', 'x').state.facets, {})
    })

    it('returns to the first page whenever the filters change', () => {
        const { listing } = build({ page: 4 })

        assert.equal(listing.toggle('product_brand', 'acme').state.page, 1)
        assert.equal(listing.goToPage(3).sortBy('price_asc').state.page, 1)
    })

    it('leaves the state it was given untouched', () => {
        const { listing } = build({ facets: { product_brand: ['acme'] } })
        const before = listing.state

        listing.toggle('product_brand', 'globex')

        assert.deepEqual(before.selected('product_brand'), ['acme'])
    })

    describe('when it gathers gestures until a submit', () => {
        it('asks the engine nothing until apply', async () => {
            const { listing, client } = build()

            listing.toggle('product_brand', 'acme').toggle('product_brand', 'globex')
            assert.equal(client.plans.length, 0)

            await listing.apply()
            assert.equal(client.plans.length, 1)
        })

        /** Sorting or paging is an order, not a filter being gathered. */
        it('still sorts, pages and clears on the spot', async () => {
            const { listing, client } = build()

            listing.sortBy('price_asc')
            listing.goToPage(2)
            listing.reset()
            await Promise.resolve()

            assert.equal(client.plans.length, 3)
        })
    })

    describe('when it searches at once', () => {
        it('asks the engine at every gesture', async () => {
            const { listing, client } = build({}, { apply: 'immediate' })

            listing.toggle('product_brand', 'acme')
            await Promise.resolve()

            assert.equal(client.plans.length, 1)
        })
    })

    it('sends the whole plan in one search', async () => {
        const { listing, client } = build()

        await listing.toggle('product_brand', 'acme').apply()

        assert.deepEqual(Object.keys(client.plans[0] ?? {}), ['results', 'count:product_brand'])
    })

    // Five ticked boxes must not cost five presses on the back button.
    it('replaces the history entry for a filter, pushes one for a page', async () => {
        const { listing, history } = build()

        await listing.toggle('product_brand', 'acme').apply()
        listing.goToPage(2)

        assert.deepEqual(history.replaced, ['/shop?brand=acme'])
        assert.deepEqual(history.pushed, ['/shop?brand=acme&pg=2'])
    })

    it('announces the state it moved to, then the answer it got', async () => {
        const { listing, heard } = build()

        await listing.toggle('product_brand', 'acme').apply()

        assert.deepEqual(heard.map(([name]) => name), ['change', 'results'])
        assert.deepEqual((heard[1]?.[1] as ResultsDetail).answers.results?.totalHits, 0)
    })

    /** Being overtaken is what cancelling is for, not something to report. */
    it('says nothing when a fresher search replaced it', async () => {
        const { listing, heard } = build({}, {}, new SearchSuperseded())

        await listing.toggle('product_brand', 'acme').apply()

        assert.deepEqual(heard.map(([name]) => name), ['change'])
    })

    it('announces a refusal of the engine', async () => {
        const { listing, heard } = build({}, {}, new SearchError('nope', { code: 'invalid_search_filter' }))

        await listing.apply()

        assert.deepEqual(heard.map(([name]) => name), ['failed'])
        assert.equal(((heard[0]?.[1] as FailedDetail).failure as SearchError).code, 'invalid_search_filter')
    })

    it('records the state it was served with as soon as it listens', () => {
        const { listing, history } = build({ facets: { product_brand: ['acme'] } })

        listing.listenToHistory()

        assert.deepEqual(history.recorded, { products: served({ facets: { product_brand: ['acme'] } }) })
    })

    it('records the state it writes with the entry', async () => {
        const { listing, history } = build()

        await listing.toggle('product_brand', 'acme').goToPage(2).apply()

        assert.deepEqual(history.recorded.products, served({ facets: { product_brand: ['acme'] }, page: 2 }))
    })

    it('repaints and searches again when the visitor goes back', async () => {
        const { listing, history, client } = build({ facets: { product_brand: ['acme'] } })

        listing.listenToHistory()
        history.goBackTo('products', served({ facets: { product_brand: ['globex'] } }))
        await Promise.resolve()

        assert.deepEqual(listing.state.selected('product_brand'), ['globex'])
        assert.equal(client.plans.length, 1)
    })

    it('writes no entry when the visitor goes back', async () => {
        const { listing, history } = build()

        listing.listenToHistory()
        history.goBackTo('products', served({ page: 2 }))
        await Promise.resolve()

        assert.deepEqual([history.replaced, history.pushed], [[], []])
    })

    it('repaints without searching when it goes back to the state it already shows', async () => {
        const { listing, history, client, heard } = build()

        await listing.toggle('product_brand', 'acme').apply()
        listing.listenToHistory()
        history.goBackTo('products', served({ facets: { product_brand: ['acme'] } }))
        await Promise.resolve()

        assert.equal(client.plans.length, 1)
        assert.equal(heard.at(-1)?.[0], 'change')
    })

    it('clears everything on reset', () => {
        const { listing } = build({ facets: { product_brand: ['acme'] }, page: 3, sort: 'price_asc' })

        assert.equal(listing.reset().state.isPristine(), true)
    })
})
