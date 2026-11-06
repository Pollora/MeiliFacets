import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { CanonicalLinkView } from '../../resources/assets/ts/listing/canonical-link-view.ts'
import { PageWindow } from '../../resources/assets/ts/pagination/page-window.ts'
import { load } from './dom.ts'

const canonicals = { first: 'https://shop.test/shop', paged: 'https://shop.test/shop/page/:page' }

const SERVED = 'https://shop.test/shop/page/3'

/** 16 cards a page. */
const canonicalOn = (asked: number, given: typeof canonicals | null = canonicals, head = `<link rel="canonical" href="${SERVED}">`) => {
    const window = load(`<div data-listing></div>`)

    window.document.head.innerHTML = head
    new CanonicalLinkView(given, window.document).show(new PageWindow({ asked, perPage: 16, total: 64, reachable: 1000 }))

    return window.document.head.querySelector('link[rel="canonical"]')?.getAttribute('href') ?? null
}

describe('CanonicalLinkView', () => {
    it('points the canonical at the page turned to', () => {
        assert.equal(canonicalOn(2), 'https://shop.test/shop/page/2')
    })

    it('points the first page at its own canonical', () => {
        assert.equal(canonicalOn(1), 'https://shop.test/shop')
    })

    it('keeps a canonical every page shares', () => {
        const shared = { first: 'https://shop.test/about', paged: 'https://shop.test/about' }

        assert.equal(canonicalOn(2, shared), 'https://shop.test/about')
    })

    it('leaves the canonical alone when the server could not tell them', () => {
        assert.equal(canonicalOn(2, null), SERVED)
    })

    it('adds none to a page served without one', () => {
        assert.equal(canonicalOn(2, canonicals, ''), null)
    })
})
