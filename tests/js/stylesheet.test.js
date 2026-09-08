import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { Contract } from '../../resources/assets/js/contract.js'
import { listingMarkup, open } from './dom.js'

/** The `[hidden]` guard is left out: happy-dom gives the attribute absolute priority, so the assertion cannot fail. */
describe('the module stylesheet', () => {
    let window
    let root

    beforeEach(() => {
        ({ window, root } = open(listingMarkup(), { styled: true }))
    })

    const style = (hook) => window.getComputedStyle(root.querySelector(Contract.selector(hook)))

    /** The rule is a list of hooks: one forgotten there is a control that reads as text. */
    it('leaves no command without it', () => {
        const commands = ['page', 'previous', 'next', 'reset', 'apply', 'sort-trigger', 'sort-option', 'input']

        assert.deepEqual(commands.filter((hook) => style(hook).cursor !== 'pointer'), [])
    })

    it('keeps the sort list above the page instead of pushing it', () => {
        assert.equal(style('sort').position, 'relative')
        assert.equal(style('sort-list').position, 'absolute')
    })

    it('strips the bullets and the indent from the lists it renders itself', () => {
        const values = window.getComputedStyle(root.querySelector('[data-meili="facets"] ul'))

        assert.equal(values.listStyle, 'none')
        assert.equal(values.paddingLeft, '0px')
        assert.equal(style('sort-list').listStyle, 'none')
    })

    it('pushes the count to the end of the row', () => {
        assert.equal(style('count').marginLeft, 'auto')
    })

    it('gives every command the same height and the same scale', () => {
        const controls = ['apply', 'reset', 'page', 'previous', 'next', 'sort-trigger'].map((hook) => style(hook))

        for (const control of controls) {
            assert.equal(control.minHeight, controls[0].minHeight)
            assert.equal(control.fontSize, controls[0].fontSize)
        }
    })

    it('hangs the checkbox on the first line, at the size of the text', () => {
        const box = window.getComputedStyle(root.querySelector(Contract.selector('input')))
        const row = window.getComputedStyle(root.querySelector(`${Contract.selector('facet-value')} label`))

        assert.equal(row.alignItems, 'center')
        assert.equal(box.alignSelf, 'flex-start')
        assert.equal(box.width, box.height)
        assert.equal(box.width, window.getComputedStyle(root.querySelector(Contract.selector('facets'))).fontSize)
    })

    it('gives every page number the same box', () => {
        assert.equal(style('page').minWidth, style('page').minHeight)
        assert.equal(style('page').fontVariantNumeric, 'tabular-nums')
    })

    it('gives the button that commits the filters the width of its column', () => {
        assert.equal(style('apply').width, '100%')
    })

    it('tells the page it is on apart from the ones it is not', () => {
        const pages = root.querySelectorAll(Contract.selector('page'))
        const current = pages[0]

        current.setAttribute('aria-current', 'page')

        assert.notEqual(window.getComputedStyle(current).fontWeight, window.getComputedStyle(pages[1]).fontWeight)
    })

    it('takes the label out of sight without taking it out of the accessible name', () => {
        const label = window.getComputedStyle(root.querySelector('[data-meili="sort"] > label'))

        assert.equal(label.position, 'absolute')
        assert.notEqual(label.display, 'none')
        assert.notEqual(label.visibility, 'hidden')
    })
})
