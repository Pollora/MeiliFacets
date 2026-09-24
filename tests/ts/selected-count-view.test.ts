import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { SelectedCountView } from '../../resources/assets/ts/collapsible/selected-count-view.ts'
import { FacetsView } from '../../resources/assets/ts/facets/facets-view.ts'
import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { find, listingMarkup, open, tick } from './dom.ts'
import { connection, described, FakeClient, FakeHistory } from './fixtures.ts'

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

const badges = (contract: Contract) =>
    (contract.all('selected-count') as HTMLElement[]).map((badge) => (badge.hidden ? null : badge.textContent))

describe('SelectedCountView', () => {
    it('counts, on each trigger, the values its own facet holds', () => {
        const { root } = open(listingMarkup({ collapsible: true }))
        const contract = new Contract(root)

        new SelectedCountView(contract, new FacetsView(contract, description))
            .show(new ListingState({ facets: { product_brand: ['acme', 'globex'] }, price: { max: 30 } }))

        assert.deepEqual(badges(contract), ['2', null])
    })

    /** The trigger is described by its badge, hidden or not: a zero left in it would still be read out. */
    it('hides and empties a badge at zero', () => {
        const { root } = open(listingMarkup({ collapsible: true }))
        const contract = new Contract(root)
        const view = new SelectedCountView(contract, new FacetsView(contract, description))

        view.show(new ListingState({ facets: { product_cat: ['coats'] } }))
        view.show(new ListingState())

        const category = contract.all('selected-count')[1] as HTMLElement
        assert.equal(category.hidden, true)
        assert.equal(category.textContent, '')
    })

    it('counts the values ticked but not yet applied, without a search', () => {
        const { window, root } = open(listingMarkup({ collapsible: true }))
        const contract = new Contract(root)
        const client = new FakeClient()
        const listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client, history: new FakeHistory() })
        new ListingBinding(contract, listing, description).start()
        const box = (value: string) => find<HTMLInputElement>(root, `input[value="${value}"]`)

        tick(window, box('acme'))
        assert.deepEqual(badges(contract), ['1', null])

        tick(window, box('globex'))
        assert.deepEqual(badges(contract), ['2', null])

        tick(window, box('acme'))
        tick(window, box('globex'))
        assert.deepEqual(badges(contract), [null, null])
        assert.equal(client.plans.length, 0)
    })
})
