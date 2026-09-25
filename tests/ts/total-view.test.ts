import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { TotalView } from '../../resources/assets/ts/listing/total-view.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { listingMarkup, open } from './dom.ts'
import { connection, described, FakeClient, FakeHistory } from './fixtures.ts'

const description = described({
    facets: [],
    params: {},
})

describe('TotalView', () => {
    let root: HTMLElement
    let contract: Contract

    beforeEach(() => {
        root = open(listingMarkup()).root
        // A drawer and the page may both show the count: every copy has to follow.
        root.insertAdjacentHTML('beforeend', '<p aria-live="polite" data-meili="total">0 items</p>')
        contract = new Contract(root)
    })

    const counters = () => contract.all('total').map((counter) => counter.textContent)

    it('writes the total on every counter the theme placed', () => {
        new TotalView(contract, description).show(88)

        assert.deepEqual(counters(), ['88 items', '88 items'])
    })

    it('picks the form the language of the page names for the count', () => {
        const view = new TotalView(contract, description)

        view.show(1)
        assert.deepEqual(counters(), ['1 item', '1 item'])

        view.show(0)
        assert.deepEqual(counters(), ['0 items', '0 items'])

        new TotalView(contract, { ...description, locale: 'fr', totalPattern: ':count article|:count articles' }).show(0)
        assert.deepEqual(counters(), ['0 article', '0 article'])
    })

    /** A rewritten live region is read out again: a page change must not repeat an unchanged count. */
    it('leaves a counter alone when its count has not changed', () => {
        const view = new TotalView(contract, description)
        view.show(12)
        const written = contract.all('total').map((counter) => counter.firstChild)

        view.show(12)

        assert.deepEqual(contract.all('total').map((counter) => counter.firstChild), written)
    })

    it('follows each search the listing answers', async () => {
        const client = new FakeClient()
        const listing = new Listing(description, connection, {
            filterQueries: filterQueriesOf(description),
            client,
            history: new FakeHistory(),
        })
        new ListingBinding(contract, listing, description).start()

        client.answer = { results: { hits: [], totalHits: 37 } }
        await listing.apply()

        assert.deepEqual(counters(), ['37 items', '37 items'])
    })
})
