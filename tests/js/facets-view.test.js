import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { Contract } from '../../resources/assets/js/contract.js'
import { FacetsView } from '../../resources/assets/js/facets-view.js'
import { ListingState } from '../../resources/assets/js/listing-state.js'
import { listingMarkup, open } from './dom.js'

const description = {
    params: { product_brand: 'brand', product_cat: 'categorie' },
    countPattern: ':count result|:count results',
    foldLabels: { more: 'Show more', less: 'Show less' },
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 3, visible: 10 },
        { taxonomy: 'product_cat', multiple: false, cap: 1, visible: 10 },
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

    /**
     * The fold is the server's at first render and nobody's after: without this,
     * the first search revealed every value the engine still counted.
     */
    it('folds again on every answer, keeping the ones that still have results', () => {
        const narrow = new FacetsView(new Contract(root), { ...description, facets: [{ taxonomy: 'product_brand', multiple: true, cap: 3, visible: 1 }] })

        narrow.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(host('acme').hidden, false)
        assert.equal(host('globex').hidden, true)
    })

    it('reads a facet in full once asked, and folds it back', () => {
        const narrow = new FacetsView(new Contract(root), { ...description, facets: [{ taxonomy: 'product_brand', multiple: true, cap: 3, visible: 1 }] })
        const button = root.querySelector(Contract.selector('more'))

        narrow.toggleFold(button)
        narrow.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(host('globex').hidden, false)
        assert.equal(button.getAttribute('aria-expanded'), 'true')
        assert.equal(button.textContent, 'Show less')

        narrow.toggleFold(button)
        narrow.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(host('globex').hidden, true)
        assert.equal(button.textContent, 'Show more')
    })

    /** Unfolding asks the engine nothing: pressing before any search must not empty the list. */
    it('reads a facet in full before any search has answered', () => {
        const button = root.querySelector(Contract.selector('more'))

        view.toggleFold(button)

        assert.equal(box('acme').closest(Contract.selector('facet-value')).hidden, false)
        assert.equal(box('coats').closest(Contract.selector('facet-value')).hidden, false)
    })

    /** A legend over nothing reads as a facet that lost its values, not as one that has none. */
    it('hides a whole facet the answer emptied, and brings it back', () => {
        const block = (value) => host(value).closest(Contract.selector('facet'))

        view.showCounts(counts({ product_brand: { acme: 3, globex: 1 } }))

        assert.equal(block('acme').hidden, false)
        assert.equal(block('coats').hidden, true)

        view.showCounts(counts({ product_brand: { acme: 3, globex: 1 }, product_cat: { coats: 2 } }))

        assert.equal(block('coats').hidden, false)
    })

    /** A held value the visitor cannot see is a filter they cannot lift. */
    it('never folds away a value the visitor holds', () => {
        const narrow = new FacetsView(new Contract(root), { ...description, facets: [{ taxonomy: 'product_brand', multiple: true, cap: 3, visible: 1 }] })

        box('globex').checked = true
        narrow.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(host('globex').hidden, false)
    })

    /** Nothing to unfold, nothing to press. */
    it('hides the button when every value is read', () => {
        const button = root.querySelector(Contract.selector('more'))

        button.hidden = false
        view.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(button.hidden, true)
    })

    /** A count read off the wrong facet would show a brand's total on a category. */
    it('counts each value under its own taxonomy', () => {
        view.showCounts(counts({ product_brand: { coats: 9 }, product_cat: { coats: 4 } }))

        assert.equal(host('coats').querySelector('[data-meili="count"]').textContent, '4 results')
    })
})
