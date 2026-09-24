import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { listingMarkup, open } from './dom.ts'

import type { StyleProperty, TestWindow } from './dom.ts'

describe('a facet placed outside the group', () => {
    let window: TestWindow
    let grouped: Element | undefined
    let apart: Element | undefined

    beforeEach(() => {
        ({ window } = open(listingMarkup(), { styled: true }))

        const facets = [...window.document.querySelectorAll('[data-meili="facet"]')]

        grouped = facets.find((facet) => facet.closest('[data-meili="facets"]') !== null)
        apart = facets.find((facet) => facet.closest('[data-meili="facets"]') === null)
    })

    const compare = (pick: (facet: Element) => Element | null, ...properties: StyleProperty[]) => {
        const of = (facet: Element | undefined) => {
            const picked = facet === undefined ? null : pick(facet)

            assert.ok(picked)

            const styles = window.getComputedStyle(picked)

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
