import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { SelectedCountView } from '../../resources/assets/ts/collapsible/selected-count-view.ts'
import { FacetsView } from '../../resources/assets/ts/facets/facets-view.ts'
import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { PriceControl } from '../../resources/assets/ts/price/price-control.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { find, listingMarkup, open, stroke, tick } from './dom.ts'
import { connection, described, FakeClient, FakeHistory } from './fixtures.ts'

const description = described({
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 5, visible: 10, labels: {}, counts: {} },
        { taxonomy: 'product_cat', multiple: false, cap: 1, visible: 10, labels: {}, counts: {} },
    ],
    params: { product_brand: 'brand', product_cat: 'categorie' },
})

const badges = (contract: Contract) =>
    (contract.all('selected-count') as HTMLElement[]).map((badge) => (badge.hidden ? null : badge.textContent))

describe('SelectedCountView', () => {
    it('counts, on each trigger, the values its own facet holds', () => {
        const { root } = open(listingMarkup({ collapsible: true }))
        const contract = new Contract(root)

        new SelectedCountView(contract, [new FacetsView(contract, description)])
            .show(new ListingState({ facets: { product_brand: ['acme', 'globex'] }, price: { max: 30 } }))

        assert.deepEqual(badges(contract), ['2', null])
    })

    /** The trigger is described by its badge, hidden or not: a zero left in it would still be read out. */
    it('hides and empties a badge at zero', () => {
        const { root } = open(listingMarkup({ collapsible: true }))
        const contract = new Contract(root)
        const view = new SelectedCountView(contract, [new FacetsView(contract, description)])

        view.show(new ListingState({ facets: { product_cat: ['coats'] } }))
        view.show(new ListingState())

        const category = contract.all('selected-count')[1] as HTMLElement
        assert.equal(category.hidden, true)
        assert.equal(category.textContent, '')
    })

    /** `R-123`: a range is one filter, whether it holds one end or two. */
    it('counts a held price range once on the price trigger', () => {
        const { root } = open(listingMarkup({ collapsible: true, priced: true }))
        const contract = new Contract(root)
        const facets = new FacetsView(contract, description)
        const view = new SelectedCountView(contract, [facets, new PriceControl(contract, description, () => {})])

        view.show(new ListingState({ price: { min: 10, max: 30 } }))
        assert.deepEqual(badges(contract), [null, null, '1'])

        view.show(new ListingState({ facets: { product_brand: ['acme'] }, price: { max: 30 } }))
        assert.deepEqual(badges(contract), ['1', null, '1'])

        view.show(new ListingState())
        assert.deepEqual(badges(contract), [null, null, null])
    })

    it('leaves alone a badge no control claims', () => {
        const { root } = open(listingMarkup({ collapsible: true, priced: true }))
        const contract = new Contract(root)
        const price = contract.all('selected-count')[2] as HTMLElement

        price.hidden = false
        price.textContent = '1'
        new SelectedCountView(contract, [new FacetsView(contract, description)]).show(new ListingState())

        assert.equal(price.textContent, '1')
    })

    it('counts a range moved but not yet applied, without a search', () => {
        const { window, root } = open(listingMarkup({ collapsible: true, priced: true }))
        const contract = new Contract(root)
        const client = new FakeClient()
        const listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client, history: new FakeHistory() })
        new ListingBinding(contract, listing, description).start()

        stroke(window, find(root, '[data-bound="max"]'), 'ArrowLeft')

        assert.deepEqual(badges(contract), [null, null, '1'])
        assert.equal(client.plans.length, 0)
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
