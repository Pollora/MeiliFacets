import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { CONTRACT, find, open } from './dom.ts'

import type { StyleProperty } from './dom.ts'

type Classes = Record<'head' | 'range' | 'track' | 'handle' | 'tip' | 'bounds', string>

const control = (classes: Classes) => `
    <div data-listing data-meili-contract="${CONTRACT}">
      <fieldset data-meili="facet">
        <p class="${classes.head}"><span data-meili="price-readout">20 € – 60 €</span></p>
        <div class="${classes.range}" data-meili="price-range" style="--from: 0.1; --to: 0.3">
          <div class="${classes.track}" data-meili="price-track">
            <button class="${classes.handle}" data-meili="price-handle" data-bound="min" role="slider">
              <span class="${classes.tip}" data-meili="price-tip">20 €</span>
            </button>
          </div>
        </div>
        <p class="${classes.bounds}"><span data-meili="price-bounds-min">0 €</span><span data-meili="price-bounds-max">199 €</span></p>
        <input data-meili="price-min" type="number" name="min_price" value="20">
      </fieldset>
    </div>`

const moduleView = control({
    head: 'meilifacetsRangeHead', range: 'meilifacetsRange', track: 'meilifacetsRangeTrack',
    handle: 'meilifacetsRangeHandle', tip: 'meilifacetsRangeTip', bounds: 'meilifacetsRangeBounds',
})

const themeOverride = control({
    head: 'shop-price-head', range: 'shop-price-range', track: 'shop-price-track',
    handle: 'shop-price-handle', tip: 'shop-price-tip', bounds: 'shop-price-bounds',
})

/** R-93: what a theme keeps when it overrides a view is the hooks, not the classes. */
describe('a price control whose view a theme overrode with its own classes', () => {
    const styleOf = (markup: string, hook: string, properties: StyleProperty[]) => {
        const { window } = open(markup, { styled: true })
        const styles = window.getComputedStyle(find(window.document, `[data-meili="${hook}"]`))

        return Object.fromEntries(properties.map((property) => [property, styles[property]]))
    }

    const same = (hook: string, ...properties: StyleProperty[]) => {
        assert.deepEqual(styleOf(themeOverride, hook, properties), styleOf(moduleView, hook, properties))
    }

    it('keeps a rail it can drag on', () => {
        same('price-range', 'position', 'display', 'touchAction', 'marginTop')
        same('price-track', 'position', 'height', 'borderTopLeftRadius')
    })

    it('keeps its handles and their tips', () => {
        same('price-handle', 'position', 'width', 'borderTopLeftRadius', 'cursor')
        same('price-tip', 'position', 'opacity', 'whiteSpace')
    })

    it('keeps its figures aligned', () => {
        for (const hook of ['price-readout', 'price-tip', 'price-bounds-min', 'price-bounds-max', 'price-min']) {
            assert.equal(styleOf(themeOverride, hook, ['fontVariantNumeric']).fontVariantNumeric, 'tabular-nums', hook)
        }
    })
})
