import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Listing } from '../../resources/assets/js/listing.js'
import { SearchError, SearchSuperseded } from '../../resources/assets/js/search-client.js'

const description = {
    name: 'products',
    perPage: 16,
    filter: 'post_type = "product"',
    apply: 'submit',
    attributes: ['card'],
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 3 },
        { taxonomy: 'product_cat', multiple: false, cap: 1 },
    ],
    params: { product_brand: 'brand' },
    reserved: {},
    sorts: { price_asc: ['metas._price:asc'] },
}

class FakeHistory {
    constructor(search = '') {
        this.current = search
        this.pushed = []
        this.replaced = []
        this.listener = null
    }

    search() {
        return this.current
    }

    path() {
        return '/shop'
    }

    replace(state, search) {
        this.replaced.push(search)
        this.current = search
    }

    push(state, search) {
        this.pushed.push(search)
        this.current = search
    }

    onPopState(listener) {
        this.listener = listener
    }

    goBackTo(search) {
        this.current = search
        this.listener()
    }
}

class FakeClient {
    constructor(failure = null) {
        this.plans = []
        this.failure = failure
    }

    async search(queries) {
        this.plans.push(queries)

        if (this.failure) {
            throw this.failure
        }

        return { results: { hits: [], totalHits: 0 } }
    }
}

const build = (search = '', overrides = {}, failure = null) => {
    const history = new FakeHistory(search)
    const client = new FakeClient(failure)
    const listing = new Listing({ ...description, ...overrides }, {}, { client, history })
    const heard = []

    for (const name of ['change', 'results', 'failed']) {
        listing.addEventListener(name, (event) => heard.push([name, event.detail]))
    }

    return { listing, history, client, heard }
}

describe('Listing', () => {
    it('starts from the state the URL carries', () => {
        const { listing } = build('?brand=acme&pg=2')

        assert.deepEqual(listing.state.selected('product_brand'), ['acme'])
        assert.equal(listing.state.page, 2)
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
        const { listing } = build('?pg=4')

        assert.equal(listing.toggle('product_brand', 'acme').state.page, 1)
        assert.equal(listing.goToPage(3).sortBy('price_asc').state.page, 1)
    })

    it('leaves the state it was given untouched', () => {
        const { listing } = build('?brand=acme')
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
    })

    describe('when it searches at once', () => {
        it('asks the engine at every gesture', async () => {
            const { listing, client } = build('', { apply: 'immediate' })

            listing.toggle('product_brand', 'acme')
            await Promise.resolve()

            assert.equal(client.plans.length, 1)
        })
    })

    it('sends the whole plan in one search', async () => {
        const { listing, client } = build()

        await listing.toggle('product_brand', 'acme').apply()

        assert.deepEqual(Object.keys(client.plans[0]), ['results', 'count:product_brand'])
    })

    // A filter replaces the entry; five ticked boxes must not cost five presses
    // on the back button.
    it('replaces the history entry for a filter, pushes one for a page', async () => {
        const { listing, history } = build()

        await listing.toggle('product_brand', 'acme').apply()
        await listing.goToPage(2).apply()

        assert.deepEqual(history.replaced, ['?brand=acme'])
        assert.deepEqual(history.pushed, ['?brand=acme&pg=2'])
    })

    it('announces the state it moved to, then the answer it got', async () => {
        const { listing, heard } = build()

        await listing.toggle('product_brand', 'acme').apply()

        assert.deepEqual(heard.map(([name]) => name), ['change', 'results'])
        assert.deepEqual(heard[1][1].answers.results.totalHits, 0)
    })

    /** Being overtaken is what cancelling is for, not something to report. */
    it('says nothing when a fresher search replaced it', async () => {
        const { listing, heard } = build('', {}, new SearchSuperseded())

        await listing.toggle('product_brand', 'acme').apply()

        assert.deepEqual(heard.map(([name]) => name), ['change'])
    })

    it('announces a refusal of the engine', async () => {
        const { listing, heard } = build('', {}, new SearchError('nope', { code: 'invalid_search_filter' }))

        await listing.apply()

        assert.deepEqual(heard.map(([name]) => name), ['failed'])
        assert.equal(heard[0][1].failure.code, 'invalid_search_filter')
    })

    it('repaints and searches again when the visitor goes back', async () => {
        const { listing, history, client } = build('?brand=acme')

        listing.listenToHistory()
        history.goBackTo('?brand=globex')
        await Promise.resolve()

        assert.deepEqual(listing.state.selected('product_brand'), ['globex'])
        assert.equal(client.plans.length, 1)
    })

    it('clears everything on reset', () => {
        const { listing } = build('?brand=acme&pg=3&sort=price_asc')

        assert.equal(listing.reset().state.isPristine(), true)
    })
})
