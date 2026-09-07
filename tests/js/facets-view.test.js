import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { Contract } from '../../resources/assets/js/contract.js'
import { FacetsView } from '../../resources/assets/js/facets-view.js'
import { ListingState } from '../../resources/assets/js/listing-state.js'
import { listingMarkup, open } from './dom.js'

const description = {
    params: { product_brand: 'brand', product_cat: 'categorie' },
    countPattern: ':count result|:count results',
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 3 },
        { taxonomy: 'product_cat', multiple: false, cap: 1 },
    ],
}

const counts = (distributions) => ({
    of: (facet) => distributions[facet.taxonomy] ?? {},
})

describe('FacetsView', () => {
    let root
    let view

    beforeEach(() => {
        root = open(listingMarkup()).root
        view = new FacetsView(new Contract(root), description)
    })

    const box = (value) => root.querySelector(`input[value="${value}"]`)
    const host = (value) => box(value).closest(Contract.selector('facet-value'))

    it('ticks the boxes the state holds, and unticks the others', () => {
        box('globex').checked = true

        view.showSelection(new ListingState({ facets: { product_brand: ['acme'] } }))

        assert.equal(box('acme').checked, true)
        assert.equal(box('globex').checked, false)
        assert.equal(box('coats').checked, false)
    })

    it('unticks a value the state no longer holds', () => {
        box('acme').checked = true

        view.showSelection(new ListingState())

        assert.equal(box('acme').checked, false)
    })

    it('writes the counts the engine returned', () => {
        view.showCounts(counts({ product_brand: { acme: 1, globex: 12 } }))

        assert.equal(root.querySelector('[data-meili="count"]').textContent, '1 result')
        assert.equal(host('globex').querySelector('[data-meili="count"]').textContent, '12 results')
    })

    it('hides a value nothing would match, and brings it back', () => {
        view.showCounts(counts({ product_brand: { acme: 3 } }))

        assert.equal(host('acme').hidden, false)
        assert.equal(host('globex').hidden, true)

        view.showCounts(counts({ product_brand: { acme: 3, globex: 1 } }))

        assert.equal(host('globex').hidden, false)
    })

    /** A count read off the wrong facet would show a brand's total on a category. */
    it('counts each value under its own taxonomy', () => {
        view.showCounts(counts({ product_brand: { coats: 9 }, product_cat: { coats: 4 } }))

        assert.equal(host('coats').querySelector('[data-meili="count"]').textContent, '4 results')
    })
})
