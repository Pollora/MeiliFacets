import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { FilterSummaryView } from '../../resources/assets/ts/listing/filter-summary-view.ts'
import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { listingMarkup, open } from './dom.ts'
import { described } from './fixtures.ts'

const description = described({ filterPattern: ':count active filter|:count active filters' })

describe('FilterSummaryView', () => {
    let contract: Contract

    beforeEach(() => {
        const { root } = open(listingMarkup())
        // The bar and the foot of a drawer may both hold them: every copy has to follow.
        root.insertAdjacentHTML('beforeend', `
            <button type="button" hidden data-meili="reset">Clear all</button>
            <span hidden data-meili="active-filters">0 active filters</span>`)
        contract = new Contract(root)
    })

    const copies = (hook: string) => contract.all(hook) as HTMLElement[]
    const hidden = (hook: string) => copies(hook).map((node) => node.hidden)

    it('paints every badge and every reset the theme placed', () => {
        new FilterSummaryView(contract, description).show(new ListingState({ facets: { product_brand: ['acme', 'globex'] } }))

        assert.deepEqual(copies('active-filters').map((badge) => badge.textContent), ['2 active filters', '2 active filters'])
        assert.deepEqual(hidden('active-filters'), [false, false])
        assert.deepEqual(hidden('reset'), [false, false])
    })

    it('hides every copy again once nothing is left', () => {
        const view = new FilterSummaryView(contract, description)

        view.show(new ListingState({ facets: { product_brand: ['acme'] } }))
        view.show(new ListingState())

        assert.deepEqual(hidden('active-filters'), [true, true])
        assert.deepEqual(hidden('reset'), [true, true])
    })
})
