import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { TotalView } from '../../resources/assets/ts/listing/total-view.ts'
import { ANNOUNCE_DELAY_MS } from '../../resources/assets/ts/shared/debounced-announcer.ts'
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
        root.insertAdjacentHTML('beforeend', '<p data-meili="total">0 items</p>')
        root.insertAdjacentHTML('beforeend', '<p aria-live="polite" data-meili="total-status"></p>')
        contract = new Contract(root)
    })

    const counters = () => contract.all('total').map((counter) => counter.textContent)
    const regions = () => contract.all('total-status').map((region) => region.textContent)

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

    it('says the total in every live region beside the counters, never the count the page was served with', () => {
        const view = new TotalView(contract, description)

        view.show(0)
        assert.deepEqual(regions(), ['', ''])

        view.show(88)
        assert.deepEqual(regions(), ['88 items', '88 items'])
    })

    it('writes the count of an answer to typing at once, and says it once typing rests', (t) => {
        t.mock.timers.enable({ apis: ['setTimeout'] })
        const view = new TotalView(contract, description)

        view.showWhileTyping(3)
        view.showWhileTyping(2)
        assert.deepEqual(counters(), ['2 items', '2 items'])
        assert.deepEqual(regions(), ['', ''])

        t.mock.timers.tick(ANNOUNCE_DELAY_MS)
        assert.deepEqual(regions(), ['2 items', '2 items'])
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
