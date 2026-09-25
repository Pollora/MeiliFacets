import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { SelectionCountView } from '../../resources/assets/ts/listing/selection-count-view.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { find, listingMarkup, open, tick } from './dom.ts'
import { connection, described, FakeClient, FakeHistory } from './fixtures.ts'

import type { ListingDescription } from '../../resources/assets/ts/shared/description.ts'

const description = described({
    name: 'products',
    perPage: 10,
    reachableHits: 1000,
    filter: 'post_type = "product"',
    apply: 'submit',
    attributes: ['card'],
    countPattern: ':count result|:count results',
    filterPattern: ':count active filter|:count active filters',
    totalPattern: ':count item|:count items',
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 5, visible: 10, labels: {}, counts: {} },
        { taxonomy: 'product_cat', multiple: false, cap: 1, visible: 10, labels: {}, counts: {} },
    ],
    params: { product_brand: 'brand', product_cat: 'categorie' },
    reserved: { sort: 'sort', query: 'q', page: 'pg', minPrice: 'min_price', maxPrice: 'max_price' },
    sorts: {},
})

/** « Filter (n) » on the opener and « Apply (X) » in the drawer: two counters on one page. */
const withCounters = () => {
    const opened = open(listingMarkup())
    opened.root.insertAdjacentHTML('beforeend', `
        <button type="button">Filter <span hidden data-meili="active-count">0</span></button>
        <button type="button">Apply <span hidden data-meili="active-count">0</span></button>`)

    return { ...opened, contract: new Contract(opened.root) }
}

const bound = (apply: ListingDescription['apply']) => {
    const { window, root, contract } = withCounters()
    const client = new FakeClient()
    const listed = { ...description, apply }
    const listing = new Listing(listed, connection, { filterQueries: filterQueriesOf(listed), client, history: new FakeHistory() })
    new ListingBinding(contract, listing, listed).start()

    return { window, root, contract, client }
}

const shown = (contract: Contract) =>
    (contract.all('active-count') as HTMLElement[]).map((counter) => (counter.hidden ? null : counter.textContent))

describe('SelectionCountView', () => {
    it('writes the bare count on every counter, a range counting once', () => {
        const { contract } = withCounters()

        new SelectionCountView(contract).show(new ListingState({ facets: { product_brand: ['acme', 'globex'] }, price: { max: 30 } }))

        assert.deepEqual(shown(contract), ['3', '3'])
    })

    it('hides every counter at zero', () => {
        const { contract } = withCounters()
        const view = new SelectionCountView(contract)

        view.show(new ListingState({ facets: { product_brand: ['acme'] } }))
        view.show(new ListingState())

        assert.deepEqual(shown(contract), [null, null])
        assert.deepEqual(contract.all('active-count').map((counter) => counter.textContent), ['', ''])
    })

    /** C-3: X is what is ticked, known without a search, so pending values count before « Apply ». */
    it('counts the values ticked but not yet applied', () => {
        const { window, root, contract, client } = bound('submit')

        tick(window, find<HTMLInputElement>(root, 'input[value="acme"]'))
        tick(window, find<HTMLInputElement>(root, 'input[value="coats"]'))

        assert.deepEqual(shown(contract), ['2', '2'])
        assert.equal(client.plans.length, 0)
    })

    it('counts each value as it is searched when the listing searches at once', () => {
        const { window, root, contract, client } = bound('immediate')

        tick(window, find<HTMLInputElement>(root, 'input[value="acme"]'))

        assert.deepEqual(shown(contract), ['1', '1'])
        assert.equal(client.plans.length, 1)
    })

    /** R-173 (5): a counter comes in when it appears, and holds still when only its number changes or when it goes. */
    it('plays its entry on the way from nothing to one, and only then', () => {
        const { contract } = withCounters()
        const played: Keyframe[][] = []
        contract.all('active-count').forEach((counter) => {
            counter.animate = ((keyframes: Keyframe[]) => {
                played.push(keyframes)

                return {} as Animation
            })
        })
        const view = new SelectionCountView(contract)

        view.show(new ListingState({ facets: { product_brand: ['acme'] } }))
        assert.equal(played.length, 2)
        assert.deepEqual(played[0], [{ opacity: 0, transform: 'scale(0.9)' }, { opacity: 1, transform: 'none' }])

        view.show(new ListingState({ facets: { product_brand: ['acme', 'globex'] } }))
        view.show(new ListingState())
        assert.equal(played.length, 2)
    })
})
