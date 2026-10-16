import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { ResultsInPlace } from '../../resources/assets/ts/site-search/results-in-place.ts'
import { find, load } from './dom.ts'

const RESULTS = `
<div id="root">
    <section id="products" data-meili="search-section"><ul id="product-list" data-meili="search-results"><li id="card">Serum</li></ul></section>
    <section id="leaving" data-meili="search-section" data-leaving><ul data-meili="search-results"><li id="copy">Serum</li></ul></section>
    <p id="empty" data-meili="search-empty">Nothing found</p>
    <p data-meili="search-unavailable" data-leaving>Unavailable</p>
</div>`

const read = () => {
    const root = find(load(RESULTS).document, '#root')

    return { root, inPlace: ResultsInPlace.read(new Contract(root)) }
}

describe('ResultsInPlace', () => {
    it('reads the sections and messages in place, leaving the copies on their way out aside', () => {
        const { inPlace } = read()

        assert.deepEqual(inPlace.sections.map((section) => section.id), ['products'])
        assert.deepEqual(inPlace.messages.map((message) => message.id), ['empty'])
    })

    it('gives the cards of a section and the section of a card, and no section to anything else', () => {
        const { root, inPlace } = read()
        const products = find(root, '#products')

        assert.deepEqual(inPlace.rowsOf(products).map((row) => row.id), ['card'])
        assert.equal(inPlace.sectionOf(find(root, '#card')), products)
        assert.equal(inPlace.sectionOf(find(root, '#copy')), null)
        assert.equal(inPlace.sectionOf(products), null)
    })
})
