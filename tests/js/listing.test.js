import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Listing } from '../../resources/assets/js/listing.js'

const listing = {
    perPage: 16,
    filter: 'post_type = "product"',
    facets: [
        { taxonomy: 'product_brand', multiple: true },
        { taxonomy: 'product_cat', multiple: false },
    ],
    params: { product_brand: 'brand' },
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
    constructor() {
        this.plans = []
    }

    async search(queries) {
        this.plans.push(queries)

        return { results: { hits: [], totalHits: 0 } }
    }
}

const build = (search = '') => {
    const history = new FakeHistory(search)
    const client = new FakeClient()

    return { listing: new Listing(listing, {}, { client, history }), history, client }
}

describe('Listing', () => {
    it('starts from the state the URL carries', () => {
        const { listing: l } = build('?brand=acme&pg=2')

        assert.deepEqual(l.state.facets.product_brand, ['acme'])
        assert.equal(l.state.page, 2)
    })

    it('adds a value, then removes it', () => {
        const { listing: l } = build()

        assert.deepEqual(l.toggle('product_brand', 'acme').state.facets.product_brand, ['acme'])
        assert.deepEqual(l.toggle('product_brand', 'acme').state.facets.product_brand, [])
    })

    it('returns to the first page whenever the filters change', () => {
        const { listing: l } = build('?pg=4')

        assert.equal(l.toggle('product_brand', 'acme').state.page, 1)
        assert.equal(l.goToPage(3).sortBy('price_asc').state.page, 1)
    })

    it('sends the whole plan in one search', async () => {
        const { listing: l, client } = build()

        await l.toggle('product_brand', 'acme').search()

        assert.equal(client.plans.length, 1)
        assert.deepEqual(Object.keys(client.plans[0]), ['results', 'count:product_brand'])
    })

    // A filter replaces the entry; five ticked boxes must not cost five presses
    // on the back button.
    it('replaces the history entry for a filter, pushes one for a page', () => {
        const { listing: l, history } = build()

        l.toggle('product_brand', 'acme').commitUrl()
        l.goToPage(2).commitPage()

        assert.deepEqual(history.replaced, ['?brand=acme'])
        assert.deepEqual(history.pushed, ['?brand=acme&pg=2'])
    })

    it('repaints when the visitor goes back', () => {
        const { listing: l, history } = build('?brand=acme')
        let repainted = 0

        l.onBack(() => repainted++)
        history.goBackTo('?brand=globex')

        assert.equal(repainted, 1)
        assert.deepEqual(l.state.facets.product_brand, ['globex'])
    })

    it('clears everything on reset', () => {
        const { listing: l } = build('?brand=acme&pg=3&sort=price_asc')

        assert.deepEqual(l.reset().state, { facets: {}, query: '', sort: null, page: 1 })
    })
})
