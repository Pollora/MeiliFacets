import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { BusyGrid } from '../../resources/assets/ts/results/busy-grid.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { SearchError, SearchSuperseded } from '../../resources/assets/ts/shared/search-client.ts'
import { find, listingMarkup, open } from './dom.ts'
import { connection, described, FakeHistory } from './fixtures.ts'

import type { Answers } from '../../resources/assets/ts/shared/search-client.ts'

const description = described({
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 3, visible: 10, labels: {}, counts: {} },
        { taxonomy: 'product_cat', multiple: false, cap: 1, visible: 10, labels: {}, counts: {} },
    ],
    params: { product_brand: 'brand', product_cat: 'categorie' },
})

const hit = (title: string) => ({ card: { title, url: `https://example.test/${title}` } })

/** ANIM-10: the grid says it is busy from the moment a search leaves until none is out. */
describe('BusyGrid', () => {
    class HeldClient {
        #ends: { answer: () => void, fail: (failure: Error) => void }[] = []

        search() {
            return new Promise<Answers>((resolve, reject) => {
                this.#ends.push({ answer: () => resolve({ results: { hits: [hit('Crème')], totalHits: 1 } }), fail: reject })
            })
        }

        end(rank: number) {
            return this.#ends[rank] as { answer: () => void, fail: (failure: Error) => void }
        }
    }

    let root: Element
    let client: HeldClient

    const bind = (apply: 'submit' | 'immediate') => {
        const listed = { ...description, apply }
        const listing = new Listing(listed, connection, { filterQueries: filterQueriesOf(listed), client, history: new FakeHistory() })

        new ListingBinding(new Contract(root), listing, listed).start()

        return listing
    }
    const busy = () => find(root, Contract.selector('results')).getAttribute('aria-busy')
    const turn = () => new Promise((resolve) => setTimeout(resolve, 0))

    beforeEach(() => {
        ({ root } = open(listingMarkup()))
        client = new HeldClient()
    })

    it('is busy while the search is out, and not once its answer is painted', async () => {
        const listing = bind('submit')

        void listing.apply()
        assert.equal(busy(), 'true')

        client.end(0).answer()
        await turn()
        assert.equal(busy(), null)
        assert.equal(find(root, Contract.selector('results')).children.length, 1)
    })

    it('is not busy any more when the engine refuses', async () => {
        const listing = bind('submit')

        void listing.apply()
        client.end(0).fail(new SearchError('nope'))
        await turn()

        assert.equal(busy(), null)
    })

    it('stays busy past an overtaken search, until the one that overtook it answers', async () => {
        const listing = bind('immediate')

        listing.toggle('product_brand', 'acme')
        listing.toggle('product_cat', 'coats')
        client.end(0).fail(new SearchSuperseded())
        await turn()
        assert.equal(busy(), 'true')

        client.end(1).answer()
        await turn()
        assert.equal(busy(), null)
    })

    it('stays still on submit while a box is only ticked', () => {
        bind('submit').toggle('product_brand', 'acme')

        assert.equal(busy(), null)
    })

    it('leaves a page without a grid alone', () => {
        const listing = new EventTarget()
        root.querySelector(Contract.selector('results'))?.remove()

        new BusyGrid(new Contract(root)).watch(listing)

        assert.doesNotThrow(() => listing.dispatchEvent(new Event('searching')))
    })
})
