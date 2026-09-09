import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { listingMarkup, open } from './dom.js'

/**
 * A template may place a facet anywhere in the listing. What it looks like must
 * not depend on where it was placed.
 */
describe('a facet placed outside the group', () => {
    let window
    let grouped
    let apart

    beforeEach(() => {
        ({ window } = open(listingMarkup(), { styled: true }))

        const facets = [...window.document.querySelectorAll('[data-meili="facet"]')]

        grouped = facets.find((facet) => facet.closest('[data-meili="facets"]') !== null)
        apart = facets.find((facet) => facet.closest('[data-meili="facets"]') === null)
    })

    const compare = (pick, ...properties) => {
        const of = (facet) => {
            const styles = window.getComputedStyle(pick(facet))

            return Object.fromEntries(properties.map((property) => [property, styles[property]]))
        }

        assert.deepEqual(of(apart), of(grouped))
    }

    it('reads at the scale of the listing, not of the page', () => {
        compare((facet) => facet, 'fontSize')
    })

    it('sheds the frame a browser puts around a fieldset', () => {
        compare((facet) => facet, 'borderTopWidth', 'paddingTop', 'marginBottom')
    })

    it('keeps its label set apart', () => {
        compare((facet) => facet.querySelector('legend'), 'fontWeight', 'paddingLeft')
    })

    it('strips the bullets and the indent from its list', () => {
        compare((facet) => facet.querySelector('ul'), 'listStyle', 'paddingLeft', 'marginTop')
    })
})
