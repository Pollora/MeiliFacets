import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { SummaryBinding } from '../../resources/assets/ts/listing/summary-binding.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { listingMarkup, open } from './dom.ts'
import { connection, described, FakeClient, FakeHistory } from './fixtures.ts'

const description = described({
    facets: [{ taxonomy: 'product_brand', multiple: true, cap: 5, visible: 10, labels: {}, counts: {} }],
    params: { product_brand: 'brand' },
})

describe('SummaryBinding', () => {
    let contract: Contract
    let summary: SummaryBinding

    beforeEach(() => {
        const { root } = open(listingMarkup())
        root.insertAdjacentHTML('beforeend', '<span hidden data-meili="active-count"></span>')
        contract = new Contract(root)
        const listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client: new FakeClient(), history: new FakeHistory() })
        summary = new SummaryBinding(contract, description, { listing, holders: [] }).start()
    })

    const text = (hook: string) => contract.one(hook)?.textContent

    it('paints what the visitor holds as the state moves, and leaves the total to the answer', () => {
        summary.showHeld(new ListingState({ facets: { product_brand: ['acme', 'globex'] } }))

        assert.equal(text('active-filters'), '2 active filters')
        assert.equal(text('active-count'), '2')
        assert.equal(text('total'), '0 items')
    })

    it('paints the total once the engine answers', () => {
        summary.showAnswered(12, new ListingState())

        assert.equal(text('total'), '12 items')
    })
})
