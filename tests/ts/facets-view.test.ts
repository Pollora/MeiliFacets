import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { FacetCounts } from '../../resources/assets/ts/facets/facet-counts.ts'
import { FacetsView } from '../../resources/assets/ts/facets/facets-view.ts'
import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { facetField } from '../../resources/assets/ts/shared/description.ts'
import { RESULTS } from '../../resources/assets/ts/shared/plan.ts'
import { closestHook, find, listingMarkup, open } from './dom.ts'
import { described } from './fixtures.ts'

const description = described({
    params: { product_brand: 'brand', product_cat: 'categorie' },
    countPattern: ':count result|:count results',
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 3, visible: 10, labels: {}, counts: { acme: 3, globex: 1 } },
        { taxonomy: 'product_cat', multiple: false, cap: 1, visible: 10, labels: {}, counts: { coats: 2 } },
    ],
})

const counts = (distributions: Record<string, Record<string, number>>) => new FacetCounts({
    [RESULTS]: {
        facetDistribution: Object.fromEntries(
            description.facets.map((facet) => [facetField(facet), distributions[facet.taxonomy] ?? {}])
        ),
    },
})

describe('FacetsView', () => {
    let root: Element
    let view: FacetsView

    beforeEach(() => {
        root = open(listingMarkup()).root
        view = new FacetsView(new Contract(root), description)
    })

    const box = (value: string) => find<HTMLInputElement>(root, `input[value="${value}"]`)
    const label = (button: Element, hook: string) => find<HTMLElement>(button, Contract.selector(hook))
    const host = (value: string) => closestHook(box(value), 'facet-value')
    const narrowed = (counts: Record<string, number> = { acme: 3, globex: 1 }) => new FacetsView(new Contract(root), described({
        ...description,
        facets: [{ taxonomy: 'product_brand', multiple: true, cap: 3, visible: 1, labels: {}, counts }],
    }))

    it('counts nothing for a value the answer does not name, whatever its slug', () => {
        box('globex').setAttribute('value', 'constructor')
        view.showCounts(counts({ product_brand: { acme: 3 } }))

        assert.equal(find(host('constructor'), Contract.selector('count')).textContent, '0 results')
    })

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

        assert.equal(find(root, '[data-meili="count"]').textContent, '1 result')
        assert.equal(find(host('globex'), '[data-meili="count"]').textContent, '12 results')
    })

    /** R-151: the box is named by `aria-labelledby`; a count written over its label would orphan that name. */
    it('writes a count without touching the name of the box it describes', () => {
        const input = find<HTMLInputElement>(host('globex'), Contract.selector('input'))
        const name = root.ownerDocument.getElementById(input.getAttribute('aria-labelledby') ?? '')

        view.showCounts(counts({ product_brand: { acme: 1, globex: 12 } }))

        assert.ok(name?.isConnected)
        assert.equal(name.textContent, 'Globex')
        assert.equal(root.ownerDocument.getElementById(input.getAttribute('aria-labelledby') ?? ''), name)
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
        const narrow = narrowed()

        narrow.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(host('acme').hidden, false)
        assert.equal(host('globex').hidden, true)
    })

    it('reads a facet in full once asked, and folds it back', () => {
        const narrow = narrowed()
        const button = find(root, Contract.selector('more'))

        narrow.toggleFold(button)
        narrow.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(host('globex').hidden, false)
        assert.equal(button.getAttribute('aria-expanded'), 'true')
        assert.equal(label(button, 'more-label').hidden, true)
        assert.equal(label(button, 'less-label').hidden, false)

        narrow.toggleFold(button)
        narrow.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(host('globex').hidden, true)
        assert.equal(label(button, 'more-label').hidden, false)
        assert.equal(label(button, 'less-label').hidden, true)
    })

    it('keeps what a view drew inside its fold button', () => {
        const narrow = narrowed()
        const button = find(root, Contract.selector('more'))

        button.insertAdjacentHTML('afterbegin', '<svg class="chevron"></svg>')
        narrow.toggleFold(button)
        narrow.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(button.querySelector('svg.chevron') !== null, true)
        assert.equal(label(button, 'less-label').hidden, false)
    })

    it('writes no label into a fold button that draws none', () => {
        const narrow = narrowed()
        const button = find(root, Contract.selector('more'))

        button.replaceChildren(button.ownerDocument.createTextNode('Voir plus'))
        narrow.toggleFold(button)
        narrow.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(button.textContent, 'Voir plus')
        assert.equal(button.getAttribute('aria-expanded'), 'true')
    })

    /** Unfolding asks the engine nothing: pressing before any search must not empty the list. */
    it('reads a facet in full before any search has answered', () => {
        const button = find(root, Contract.selector('more'))

        view.toggleFold(button)

        assert.equal(host('acme').hidden, false)
        assert.equal(host('coats').hidden, false)
    })

    it('keeps a value served without results hidden when the facet unfolds before any search', () => {
        const unfolding = narrowed({ acme: 3 })

        unfolding.toggleFold(find(root, Contract.selector('more')))

        assert.equal(host('acme').hidden, false)
        assert.equal(host('globex').hidden, true)
    })

    it('gives no place before the fold to a value served without results', () => {
        const button = find(root, Contract.selector('more'))
        const unfolding = narrowed({ globex: 2 })

        unfolding.toggleFold(button)
        unfolding.toggleFold(button)

        assert.equal(host('acme').hidden, true)
        assert.equal(host('globex').hidden, false)
        assert.equal(button.hidden, true)
    })

    /** A legend over nothing reads as a facet that lost its values, not as one that has none. */
    it('hides a whole facet the answer emptied, and brings it back', () => {
        const block = (value: string) => closestHook(box(value), 'facet')

        view.showCounts(counts({ product_brand: { acme: 3, globex: 1 } }))

        assert.equal(block('acme').hidden, false)
        assert.equal(block('coats').hidden, true)

        view.showCounts(counts({ product_brand: { acme: 3, globex: 1 }, product_cat: { coats: 2 } }))

        assert.equal(block('coats').hidden, false)
    })

    /** A held value the visitor cannot see is a filter they cannot lift. */
    it('never folds away a value the visitor holds', () => {
        const narrow = narrowed()

        box('globex').checked = true
        narrow.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(host('globex').hidden, false)
    })

    it('counts a held value among the places before the fold', () => {
        const narrow = narrowed()

        box('acme').checked = true
        narrow.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(host('globex').hidden, true)
        assert.equal(find(root, Contract.selector('more')).hidden, false)
    })

    it('offers no button when every value past the fold is held', () => {
        const narrow = narrowed()

        box('acme').checked = true
        box('globex').checked = true
        narrow.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(find(root, Contract.selector('more')).hidden, true)
    })

    /** Nothing to unfold, nothing to press. */
    it('hides the button when every value is read', () => {
        const button = find(root, Contract.selector('more'))

        button.hidden = false
        view.showCounts(counts({ product_brand: { acme: 5, globex: 2 } }))

        assert.equal(button.hidden, true)
    })

    /** A count read off the wrong facet would show a brand's total on a category. */
    it('counts each value under its own taxonomy', () => {
        view.showCounts(counts({ product_brand: { coats: 9 }, product_cat: { coats: 4 } }))

        assert.equal(find(host('coats'), '[data-meili="count"]').textContent, '4 results')
    })

    /** R-173 (6): nothing moves under the finger while a panel is open. */
    describe('while its panel is open', () => {
        beforeEach(() => {
            root = open(listingMarkup({ collapsible: true })).root
            view = new FacetsView(new Contract(root), description)
            find(root, '[aria-controls="panel-brand"]').setAttribute('aria-expanded', 'true')
        })

        it('keeps in place, out of reach, a value that falls to no result', () => {
            view.showCounts(counts({ product_brand: { acme: 3 } }))

            assert.equal(host('globex').hidden, false)
            assert.equal(box('globex').getAttribute('aria-disabled'), 'true')
            assert.equal(box('acme').hasAttribute('aria-disabled'), false)
        })

        it('keeps the box focusable, never `disabled`', () => {
            view.showCounts(counts({ product_brand: { acme: 3 } }))

            assert.equal(box('globex').disabled, false)
        })

        it('refuses a tick on a value out of reach, and never the untick of a held one', () => {
            view.showCounts(counts({ product_brand: { acme: 3 } }))
            box('globex').checked = true

            assert.equal(view.refuses(box('globex')), true)
            assert.equal(view.refuses(box('acme')), false)

            box('globex').checked = false

            assert.equal(view.refuses(box('globex')), false)
        })

        it('gives the value back once its count returns', () => {
            view.showCounts(counts({ product_brand: { acme: 3 } }))
            view.showCounts(counts({ product_brand: { acme: 3, globex: 1 } }))

            assert.equal(box('globex').hasAttribute('aria-disabled'), false)
        })

        it('never takes a held value out of reach', () => {
            box('globex').checked = true

            view.showCounts(counts({ product_brand: { acme: 3 } }))

            assert.equal(box('globex').hasAttribute('aria-disabled'), false)
        })

        it('brings back no value that was already out of sight', () => {
            view.showCounts(counts({ product_brand: { acme: 3 } }))
            host('globex').hidden = true

            view.showCounts(counts({ product_brand: { acme: 3 } }))

            assert.equal(host('globex').hidden, true)
            assert.equal(box('globex').hasAttribute('aria-disabled'), false)
        })

        it('lets the value go once the panel has closed', () => {
            view.showCounts(counts({ product_brand: { acme: 3 } }))
            find(root, '[aria-controls="panel-brand"]').setAttribute('aria-expanded', 'false')

            view.refold()

            assert.equal(host('globex').hidden, true)
            assert.equal(box('globex').hasAttribute('aria-disabled'), false)
        })

        it('hides a value of a closed panel as before', () => {
            view.showCounts(counts({ product_brand: { acme: 3 }, product_cat: {} }))

            assert.equal(host('coats').hidden, true)
        })
    })
})
