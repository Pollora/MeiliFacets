import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { RobotsMetaView } from '../../resources/assets/ts/listing/robots-meta-view.ts'
import { load } from './dom.ts'

import type { StateChanges } from '../../resources/assets/ts/listing/listing-state.ts'

const robots = { bare: 'index, follow, max-image-preview:large', secondary: 'noindex, follow' }

const SERVED = 'noindex, follow'

const robotsOn = (changes: StateChanges, given: typeof robots | null = robots, head = `<meta name="robots" content="${SERVED}">`) => {
    const window = load('<div data-listing></div>')

    window.document.head.innerHTML = head
    new RobotsMetaView(given, window.document).show(new ListingState(changes))

    return window.document.head.querySelector('meta[name="robots"]')?.getAttribute('content') ?? null
}

describe('RobotsMetaView', () => {
    it('lets the bare view be indexed', () => {
        assert.equal(robotsOn({}), robots.bare)
    })

    it('keeps a page past the first out of the index', () => {
        assert.equal(robotsOn({ page: 2 }), robots.secondary)
    })

    it('keeps a filtered, sorted or searched view out of the index', () => {
        assert.deepEqual(
            [robotsOn({ facets: { product_brand: ['acme'] } }), robotsOn({ sort: 'price_asc' }), robotsOn({ query: 'cream' })],
            [robots.secondary, robots.secondary, robots.secondary]
        )
    })

    it('leaves the directives alone when the server could not tell them', () => {
        assert.equal(robotsOn({}, null), SERVED)
    })

    it('adds no tag to a page served without one', () => {
        assert.equal(robotsOn({}, robots, ''), null)
    })
})
