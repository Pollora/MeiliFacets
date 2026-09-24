import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { find, listingMarkup, open } from './dom.ts'

import type { TestWindow } from './dom.ts'

/** The `[hidden]` guard is left out: happy-dom gives the attribute absolute priority, so the assertion cannot fail. */
describe('the module stylesheet', () => {
    let window: TestWindow
    let root: Element

    beforeEach(() => {
        ({ window, root } = open(listingMarkup(), { styled: true }))
    })

    const style = (hook: string) => window.getComputedStyle(find(root, Contract.selector(hook)))

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
        const values = window.getComputedStyle(find(root, '[data-meili="facets"] ul'))

        assert.equal(values.listStyle, 'none')
        assert.equal(values.paddingLeft, '0px')
        assert.equal(style('sort-list').listStyle, 'none')
    })

    it('pushes the count to the end of the row', () => {
        assert.equal(style('count').marginLeft, 'auto')
    })

    it('gives every command the same height and the same scale', () => {
        const controls = ['apply', 'reset', 'page', 'previous', 'next', 'sort-trigger'].map((hook) => style(hook))

        const [first] = controls

        for (const control of controls) {
            assert.equal(control.minHeight, first?.minHeight)
            assert.equal(control.fontSize, first?.fontSize)
        }
    })

    it('hangs the checkbox on the first line, at the size of the text', () => {
        const box = window.getComputedStyle(find(root, Contract.selector('input')))
        const row = window.getComputedStyle(find(root, `${Contract.selector('facet-value')} label`))

        assert.equal(row.alignItems, 'center')
        assert.equal(box.alignSelf, 'flex-start')
        assert.equal(box.width, box.height)
        assert.equal(box.width, window.getComputedStyle(find(root, Contract.selector('facets'))).fontSize)
    })

    it('gives every page number the same box', () => {
        assert.equal(style('page').minWidth, style('page').minHeight)
        assert.equal(style('page').fontVariantNumeric, 'tabular-nums')
    })

    it('gives the button that commits the filters the width of its column', () => {
        assert.equal(style('apply').width, '100%')
    })

    it('tells the page it is on apart from the ones it is not', () => {
        const [current, other] = root.querySelectorAll(Contract.selector('page'))

        assert.ok(current && other)
        current.setAttribute('aria-current', 'page')

        assert.notEqual(window.getComputedStyle(current).fontWeight, window.getComputedStyle(other).fontWeight)
    })

    it('takes the label out of sight without taking it out of the accessible name', () => {
        const label = window.getComputedStyle(find(root, '[data-meili="sort"] > label'))

        assert.equal(label.position, 'absolute')
        assert.notEqual(label.display, 'none')
        assert.notEqual(label.visibility, 'hidden')
    })
})
