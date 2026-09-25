import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { click, find, listingMarkup, nth, open, stroke } from './dom.ts'
import { connection, described, FakeClient, FakeHistory } from './fixtures.ts'

import type { TestWindow } from './dom.ts'

const description = described({
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 5, visible: 10, labels: {}, counts: {} },
        { taxonomy: 'product_cat', multiple: false, cap: 1, visible: 10, labels: {}, counts: {} },
    ],
    params: { product_brand: 'brand', product_cat: 'categorie' },
})

const TRACK_WIDTH = 199

/** Step 4c of the filter bar: the price as one more collapsible filter. */
describe('a price range in a collapsible panel', () => {
    let window: TestWindow
    let root: HTMLElement

    const toggle = (rank: number) => nth(root, Contract.selector('toggle'), rank)
    const panel = (rank: number) => find(root, `#${toggle(rank).getAttribute('aria-controls')}`)
    const isOpen = (rank: number) => toggle(rank).getAttribute('aria-expanded') === 'true' && !panel(rank).hidden
    const handle = (bound: string) => find(root, `[data-bound="${bound}"]`)
    const track = () => find(root, '[data-meili="price-track"]')
    const held = (bound: string) => find<HTMLInputElement>(root, `[data-meili="price-${bound}"]`).value
    const PRICE = 2

    /** happy-dom has no layout: the track measures what a browser would, nothing while its panel is closed. */
    const laidOut = () => {
        track().getBoundingClientRect = () =>
            ({ left: 0, top: 0, height: 10, width: panel(PRICE).hidden ? 0 : TRACK_WIDTH }) as DOMRect
    }

    const at = (type: string, node: Element, clientX: number) =>
        node.dispatchEvent(new window.PointerEvent(type, { bubbles: true, pointerId: 1, clientX, buttons: 1 }))

    beforeEach(() => {
        ({ window, root } = open(listingMarkup({ collapsible: true, priced: true }), { styled: true }))
        laidOut()

        const listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client: new FakeClient(), history: new FakeHistory() })
        new ListingBinding(new Contract(root), listing, description).start()
    })

    it('opens alone, closing the facet open before it, and is closed by the next', () => {
        click(window, toggle(0))
        click(window, toggle(PRICE))

        assert.equal(isOpen(0), false)
        assert.equal(isOpen(PRICE), true)

        click(window, toggle(1))

        assert.equal(isOpen(PRICE), false)
        assert.equal(isOpen(1), true)
    })

    it('measures its track when a handle moves, once the panel is open', () => {
        click(window, toggle(PRICE))

        at('pointerdown', handle('max'), TRACK_WIDTH)
        at('pointermove', track(), 100)
        at('pointerup', track(), 100)

        assert.equal(held('max'), '100')
        assert.equal(handle('max').style.getPropertyValue('--at'), '0.5025')
        assert.equal(find(root, '[data-meili="selected-count"]:not([hidden])').textContent, '1')
    })

    it('draws its handles as a share of the track, so a closed panel leaves them in place', () => {
        assert.equal(handle('min').style.getPropertyValue('--at'), '0')
        assert.equal(handle('max').style.getPropertyValue('--at'), '1')

        click(window, toggle(PRICE))

        assert.equal(handle('max').style.getPropertyValue('--at'), '1')
    })

    it('steps a handle from the keyboard once the panel is open', () => {
        click(window, toggle(PRICE))
        stroke(window, handle('max'), 'ArrowLeft')

        assert.equal(held('max'), '198')
        assert.equal(isOpen(PRICE), true)
    })
})
