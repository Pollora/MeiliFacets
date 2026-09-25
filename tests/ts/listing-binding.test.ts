import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { click, clickFromKeyboard, closestHook, find, listingMarkup, nth, open, tick, watchScrolling } from './dom.ts'
import { connection, described, FakeClient, FakeHistory, served } from './fixtures.ts'

import type { TestWindow } from './dom.ts'

const description = described({
    facets: [
        // Capped at one on purpose: the state refuses the second tick, and the boxes must say so.
        { taxonomy: 'product_brand', multiple: true, cap: 1, visible: 10, labels: {}, counts: {} },
        { taxonomy: 'product_cat', multiple: false, cap: 1, visible: 10, labels: {}, counts: {} },
    ],
    params: { product_brand: 'brand', product_cat: 'categorie' },
    sorts: { price_asc: ['metas._price:asc'], newest: ['date:desc'] },
})

const hit = (title: string) => ({ card: { title, url: `https://example.test/${title}` } })

const priceBlock = `
    <fieldset data-meili="facet">
      <div data-meili="price-range">
        <div data-meili="price-track">
          <button data-meili="price-handle" data-bound="min" aria-valuemin="0" aria-valuemax="199" aria-valuenow="0">
            <span data-meili="price-tip"></span>
          </button>
          <button data-meili="price-handle" data-bound="max" aria-valuemin="0" aria-valuemax="199" aria-valuenow="199">
            <span data-meili="price-tip"></span>
          </button>
        </div>
      </div>
      <span data-meili="price-bounds-min">0,00 €</span><span data-meili="price-bounds-max">199,00 €</span>
      <input data-meili="price-min" type="number" name="min_price" value="0">
      <input data-meili="price-max" type="number" name="max_price" value="199">
    </fieldset>`

describe('ListingBinding', () => {
    let window: TestWindow
    let root: Element
    let client: FakeClient
    let history: FakeHistory
    let listing: Listing

    beforeEach(() => {
        ({ window, root } = open(listingMarkup()))
        client = new FakeClient()
        history = new FakeHistory()
        listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client, history })

        new ListingBinding(new Contract(root), listing, description).start()
    })

    const box = (value: string) => find<HTMLInputElement>(root, `input[value="${value}"]`)
    const one = (hook: string) => find(root, Contract.selector(hook))
    const all = (hook: string) => [...root.querySelectorAll<HTMLElement>(Contract.selector(hook))]
    const second = (hook: string) => nth(root, Contract.selector(hook), 1)
    const cards = () => all('card').map((card) => find(card, '[data-meili="title"]').textContent)

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

    it('tells a page past the end from a search that found nothing', async () => {
        client.answer = { results: { hits: [], totalHits: 17 } }
        listing.goToPage(99)
        await Promise.resolve()

        assert.equal(one('no-results').hidden, true)
        assert.equal(one('past-the-end').hidden, false)

        client.answer = { results: { hits: [], totalHits: 0 } }
        await listing.apply()

        assert.equal(one('no-results').hidden, false)
        assert.equal(one('past-the-end').hidden, true)
    })

    it('leaves the message alone in a view that renders a single one', async () => {
        const empty = one('empty')

        empty.replaceChildren('Nothing to see')
        client.answer = { results: { hits: [], totalHits: 17 } }
        listing.goToPage(99)
        await Promise.resolve()

        assert.equal(empty.hidden, false)
        assert.equal(empty.textContent, 'Nothing to see')
    })

    it('writes the counts the engine returned', async () => {
        client.answer = {
            results: { hits: [], totalHits: 12, facetDistribution: { 'facets.product_brand': { acme: 12 } } },
        }

        await listing.apply()

        assert.equal(one('count').textContent, '12 results')
        assert.equal(closestHook(box('globex'), 'facet-value').hidden, true)
    })

    it('fills the pagination from what the engine counted', async () => {
        client.answer = { results: { hits: [], totalHits: 25 } }

        await listing.apply()

        assert.equal(one('pagination').hidden, false)
        assert.deepEqual(
            all('page')
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

        click(window, second('page'))
        await Promise.resolve()

        assert.equal(listing.state.page, 2)
        assert.deepEqual(history.pushed, ['/shop?pg=2'])
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
        click(window, second('sort-option'))
        await Promise.resolve()

        assert.equal(listing.state.sort, 'price_asc')
        assert.equal(one('sort-trigger').textContent, 'Price, low to high')
        assert.equal(client.plans.length, 1)
    })

    describe('when a gesture replaces the grid', () => {
        /** Each control is asked for on its own component, and answers for itself alone. */
        const gestures: Record<string, () => Element> = {
            pagination: () => second('page'),
            sort: () => second('sort-option'),
            reset: () => one('reset'),
            facets: () => one('apply'),
        }

        const start = async (scroll: string[]) => {
            ({ window, root } = open(listingMarkup({ scroll })))
            client = new FakeClient()
            history = new FakeHistory()
            listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client, history })
            new ListingBinding(new Contract(root), listing, description).start()

            // The pagination carries no page number until the engine has answered once.
            client.answer = { results: { hits: [], totalHits: 25 } }
            await listing.apply()

            return watchScrolling(root)
        }

        it('holds the page still on every control, by default', async () => {
            const scrolled = await start([])

            for (const reach of Object.values(gestures)) {
                click(window, reach())
            }

            assert.deepEqual(scrolled, [])
        })

        for (const [component, reach] of Object.entries(gestures)) {
            it(`brings the visitor back to the top when ${component} asks for it`, async () => {
                const scrolled = await start([component])

                click(window, reach())

                assert.deepEqual(scrolled, [{ block: 'start' }])
            })

            it(`leaves ${component} alone while the others ask for it`, async () => {
                const others = Object.keys(gestures).filter((name) => name !== component)
                const scrolled = await start(others)

                click(window, reach())

                assert.deepEqual(scrolled, [])
            })
        }

        /** Opening the list is not a gesture on the grid: the page must hold still. */
        it('holds still when the sort list is merely opened', async () => {
            const scrolled = await start(['sort'])

            click(window, one('sort-trigger'))

            assert.deepEqual(scrolled, [])
        })

        /** The keyboard holds its place with the focus: moving the page would hide the button. */
        it('leaves the page alone when the keyboard raised the click', async () => {
            const scrolled = await start(['pagination'])

            clickFromKeyboard(window, second('page'))

            assert.deepEqual(scrolled, [])
        })

        it('never moves the page for a ticked box', async () => {
            const scrolled = await start(['facets'])

            tick(window, box('acme'))

            assert.deepEqual(scrolled, [])
        })
    })

    /** Going back must move the boxes too, or the URL and the form disagree. */
    it('puts the boxes back where the history entry left them', async () => {
        tick(window, box('acme'))
        await listing.apply()

        history.goBackTo('products', served({ facets: { product_brand: ['globex'] } }))
        await Promise.resolve()

        assert.equal(box('acme').checked, false)
        assert.equal(box('globex').checked, true)
    })
})

describe('ListingBinding with a price range', () => {
    it('draws the bounds the engine measured for the filtered listing', async () => {
        const { root } = open(listingMarkup())
        const priced = described({
            ...description,
            priceFields: { min: 'price.min', max: 'price.max' },
            money: { format: '%2$s %1$s', symbol: '€', decimals: 2, decimal: ',', thousand: ' ' },
        })
        find(root, '[data-meili="facets"]').insertAdjacentHTML('afterbegin', priceBlock)
        const client = new FakeClient()
        const listing = new Listing(priced, connection, { filterQueries: filterQueriesOf(priced), client, history: new FakeHistory() })
        new ListingBinding(new Contract(root), listing, priced).start()

        client.answer = {
            results: { hits: [], totalHits: 7, facetStats: { 'price.min': { min: 43.4, max: 190 }, 'price.max': { min: 45, max: 199 } } },
        }
        await listing.apply()

        const ends = [...root.querySelectorAll('[data-meili^="price-bounds-"]')].map((end) => end.textContent)
        assert.deepEqual(ends, ['43,00 €', '199,00 €'])
        assert.equal(find(root, '[data-bound="min"]').getAttribute('aria-valuemin'), '43')
    })
})

describe('ListingBinding with a sort that filters', () => {
    it('hides the option once the engine says it would keep nothing, and keeps it while in use', async () => {
        const { root } = open(listingMarkup())
        const promoted = described({
            ...description,
            sorts: { ...description.sorts, on_sale: [] },
            sortFilters: { on_sale: { field: 'price.onsale', value: 'true' } },
        })
        find(root, Contract.selector('sort-list')).insertAdjacentHTML('beforeend', `
            <li id="sort-on_sale" role="option" data-value="on_sale" aria-selected="false" data-meili="sort-option">On sale</li>`)
        const client = new FakeClient()
        const listing = new Listing(promoted, connection, { filterQueries: filterQueriesOf(promoted), client, history: new FakeHistory() })
        new ListingBinding(new Contract(root), listing, promoted).start()

        client.answer = { results: { hits: [], totalHits: 4, facetDistribution: { 'price.onsale': { false: 4 } } } }
        await listing.apply()

        assert.equal(find(root, '[data-value="on_sale"]').hidden, true)

        listing.sortBy('on_sale')
        await Promise.resolve()

        assert.equal(find(root, '[data-value="on_sale"]').hidden, false)
    })
})
