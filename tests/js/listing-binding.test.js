import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { Contract } from '../../resources/assets/js/contract.js'
import { Listing } from '../../resources/assets/js/listing.js'
import { ListingBinding } from '../../resources/assets/js/listing-binding.js'
import { click, clickFromKeyboard, listingMarkup, open, tick, watchScrolling } from './dom.js'

const description = {
    name: 'products',
    perPage: 10,
    reachableHits: 1000,
    filter: 'post_type = "product"',
    apply: 'submit',
    attributes: ['card'],
    countPattern: ':count result|:count results',
    filterPattern: ':count active filter|:count active filters',
    facets: [
        // Capped at one on purpose: the state refuses the second tick, and the boxes must say so.
        { taxonomy: 'product_brand', multiple: true, cap: 1 },
        { taxonomy: 'product_cat', multiple: false, cap: 1 },
    ],
    params: { product_brand: 'brand', product_cat: 'categorie' },
    reserved: { sort: 'sort', query: 'q', page: 'pg' },
    sorts: { price_asc: ['metas._price:asc'], newest: ['date:desc'] },
}

class FakeHistory {
    constructor() {
        this.current = ''
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
        this.answer = { results: { hits: [], totalHits: 0 } }
    }

    async search(queries) {
        this.plans.push(queries)

        return this.answer
    }
}

const hit = (title) => ({ card: { title, url: `https://example.test/${title}` } })

describe('ListingBinding', () => {
    let window
    let root
    let client
    let history
    let listing

    beforeEach(() => {
        ({ window, root } = open(listingMarkup()))
        client = new FakeClient()
        history = new FakeHistory()
        listing = new Listing(description, {}, { client, history })

        new ListingBinding(root, new Contract(root), listing, description).start()
    })

    const box = (value) => root.querySelector(`input[value="${value}"]`)
    const one = (hook) => root.querySelector(Contract.selector(hook))
    const cards = () => [...root.querySelectorAll('[data-meili="card"]')]
        .map((card) => card.querySelector('[data-meili="title"]').textContent)

    it('carries a ticked box into the state', () => {
        tick(window, box('acme'))

        assert.deepEqual(listing.state.selected('product_brand'), ['acme'])
        assert.equal(client.plans.length, 0)
    })

    /** The fix: the state decides what is ticked, not the last click. */
    it('unticks a box the state refused to keep', () => {
        tick(window, box('acme'))
        tick(window, box('globex'))

        assert.deepEqual(listing.state.selected('product_brand'), ['globex'])
        assert.equal(box('acme').checked, false)
    })

    it('searches on apply and repaints the grid', async () => {
        client.answer = { results: { hits: [hit('First'), hit('Second')], totalHits: 2 } }

        tick(window, box('acme'))
        await listing.apply()

        assert.deepEqual(cards(), ['First', 'Second'])
        assert.equal(one('results').hidden, false)
        assert.equal(one('empty').hidden, true)
    })

    it('says so when nothing matches', async () => {
        await listing.apply()

        assert.deepEqual(cards(), [])
        assert.equal(one('results').hidden, true)
        assert.equal(one('empty').hidden, false)
    })

    it('writes the counts the engine returned', async () => {
        client.answer = {
            results: { hits: [], totalHits: 12, facetDistribution: { 'facets.product_brand': { acme: 12 } } },
        }

        await listing.apply()

        assert.equal(one('count').textContent, '12 results')
        assert.equal(box('globex').closest(Contract.selector('facet-value')).hidden, true)
    })

    it('fills the pagination from what the engine counted', async () => {
        client.answer = { results: { hits: [], totalHits: 25 } }

        await listing.apply()

        assert.equal(one('pagination').hidden, false)
        assert.deepEqual(
            [...root.querySelectorAll(Contract.selector('page'))]
                .filter((button) => !button.hidden)
                .map((button) => button.textContent),
            ['1', '2', '3']
        )
    })

    /** A bare digit names nothing: the badge says what it counts. */
    it('counts the filters in words, and offers to clear them', () => {
        tick(window, box('acme'))

        assert.equal(one('reset').hidden, false)
        assert.equal(one('active-filters').hidden, false)
        assert.equal(one('active-filters').textContent, '1 active filter')

        tick(window, box('coats'))

        assert.equal(one('active-filters').textContent, '2 active filters')
    })

    it('clears everything on the spot when the visitor asks', async () => {
        tick(window, box('acme'))
        click(window, one('reset'))
        await Promise.resolve()

        assert.equal(box('acme').checked, false)
        assert.equal(one('reset').hidden, true)
        assert.equal(listing.state.isPristine(), true)
        assert.equal(client.plans.length, 1)
    })

    it('goes to the page that was pressed, and leaves a way back', async () => {
        client.answer = { results: { hits: [], totalHits: 25 } }
        await listing.apply()

        click(window, [...root.querySelectorAll(Contract.selector('page'))][1])
        await Promise.resolve()

        assert.equal(listing.state.page, 2)
        assert.deepEqual(history.pushed, ['?pg=2'])
    })

    it('follows the previous and next steps', async () => {
        client.answer = { results: { hits: [], totalHits: 25 } }
        await listing.apply()

        click(window, one('next'))
        await Promise.resolve()

        assert.equal(listing.state.page, 2)

        click(window, one('previous'))
        await Promise.resolve()

        assert.equal(listing.state.page, 1)
    })

    it('sorts from the combobox, without waiting for a submit', async () => {
        click(window, one('sort-trigger'))
        click(window, [...root.querySelectorAll(Contract.selector('sort-option'))][1])
        await Promise.resolve()

        assert.equal(listing.state.sort, 'price_asc')
        assert.equal(one('sort-trigger').textContent, 'Price, low to high')
        assert.equal(client.plans.length, 1)
    })

    describe('when a gesture replaces the grid', () => {
        let scrolled

        // The pagination carries no page number until the engine has answered once.
        beforeEach(async () => {
            client.answer = { results: { hits: [], totalHits: 25 } }
            await listing.apply()
            scrolled = watchScrolling(root)
        })

        it('brings the visitor back to the top of the listing', () => {
            click(window, [...root.querySelectorAll(Contract.selector('page'))][1])

            assert.deepEqual(scrolled, [{ block: 'start' }])
        })

        it('does the same for the apply button, the reset and a picked order', () => {
            for (const node of [one('apply'), one('reset'), [...root.querySelectorAll(Contract.selector('sort-option'))][1]]) {
                scrolled.length = 0
                click(window, node)

                assert.equal(scrolled.length, 1, node.getAttribute('data-meili'))
            }
        })

        /** Opening the list is not a gesture on the grid: the page must hold still. */
        it('holds still when the sort list is merely opened', () => {
            click(window, one('sort-trigger'))

            assert.deepEqual(scrolled, [])
        })

        /** The keyboard holds its place with the focus: moving the page would hide the button. */
        it('leaves the page alone when the keyboard raised the click', () => {
            clickFromKeyboard(window, [...root.querySelectorAll(Contract.selector('page'))][1])

            assert.deepEqual(scrolled, [])
        })

        it('never moves the page for a ticked box', () => {
            tick(window, box('acme'))

            assert.deepEqual(scrolled, [])
        })
    })

    /** Going back must move the boxes too, or the URL and the form disagree. */
    it('puts the boxes back where the history entry left them', async () => {
        tick(window, box('acme'))
        await listing.apply()

        history.goBackTo('?brand=globex')
        await Promise.resolve()

        assert.equal(box('acme').checked, false)
        assert.equal(box('globex').checked, true)
    })
})
