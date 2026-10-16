import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { ResetView } from '../../resources/assets/ts/listing/reset-view.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { listingMarkup, open } from './dom.ts'

describe('ResetView', () => {
    let contract: Contract

    beforeEach(() => {
        const { root } = open(listingMarkup())
        // The bar and the foot of a drawer may both hold one: every copy has to follow.
        root.insertAdjacentHTML('beforeend', '<button type="button" hidden data-meili="reset">Clear all</button>')
        contract = new Contract(root)
    })

    const hidden = () => contract.all('reset').map((reset) => (reset as HTMLElement).hidden)

    it('shows every reset the theme placed while a filter is held', () => {
        new ResetView(contract).show(new ListingState({ facets: { product_brand: ['acme'] } }))

        assert.deepEqual(hidden(), [false, false])
    })

    it('hides every copy again once nothing is left', () => {
        const view = new ResetView(contract)

        view.show(new ListingState({ facets: { product_brand: ['acme'] } }))
        view.show(new ListingState())

        assert.deepEqual(hidden(), [true, true])
    })
})
