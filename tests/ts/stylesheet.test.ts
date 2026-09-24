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
        find(root, Contract.selector('active-values')).insertAdjacentHTML('afterbegin', '<li><button data-meili="active-value">Acme</button></li>')
        const commands = ['page', 'previous', 'next', 'reset', 'apply', 'sort-trigger', 'sort-option', 'input', 'more', 'active-value']

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

    /**
     * R-168: the fold button reads as one more row of values, at the scale of the other commands.
     * Spacing is compared as declared: happy-dom resolves `em` against the wrong size inside the label.
     */
    it('draws the fold button as a row of values rather than a native button', () => {
        const rules = [...window.document.styleSheets].flatMap((sheet) => [...sheet.cssRules])
        const declared = (selector: string) => {
            const rule = rules.find((candidate) => candidate instanceof window.CSSStyleRule && candidate.selectorText === selector)

            assert.ok(rule instanceof window.CSSStyleRule, selector)

            return rule.style
        }
        const more = declared(Contract.selector('more'))
        const row = declared(`${Contract.selector('facet-value')} label`)

        assert.equal(style('more').fontSize, style('reset').fontSize)
        assert.equal(style('more').fontFamily, style('reset').fontFamily)
        assert.equal(style('more').borderTopWidth, '0px')
        assert.equal(style('more').backgroundColor, 'transparent')
        assert.equal(more.marginLeft, row.marginLeft)
        assert.equal(more.padding, row.padding)
    })

    /** The count is rewritten at every search: proportional digits would shift it sideways. */
    it('draws the count in digits of one width', () => {
        assert.equal(style('count').fontVariantNumeric, 'tabular-nums')
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

    describe('a facet presented as pills', () => {
        const pills = () => {
            const facet = find(root, Contract.selector('facet'))

            facet.setAttribute('data-presentation', 'pill')

            return facet
        }
        const computed = (element: Element) => window.getComputedStyle(element)

        it('lines its values up and wraps them', () => {
            const values = computed(find(pills(), 'ul'))

            assert.equal(values.display, 'flex')
            assert.equal(values.flexWrap, 'wrap')
        })

        it('draws each value like the pill of an active filter, at the height of a command', () => {
            find(root, Contract.selector('active-values')).insertAdjacentHTML('afterbegin', '<li><button data-meili="active-value">Acme</button></li>')
            const pill = computed(find(pills(), `${Contract.selector('facet-value')} label`))

            assert.equal(pill.borderRadius, style('active-value').borderRadius)
            assert.equal(pill.minHeight, style('active-value').minHeight)
            assert.equal(pill.minHeight, style('reset').minHeight)
        })

        it('keeps the native input to check, out of sight but not out of reach', () => {
            const input = computed(find(pills(), Contract.selector('input')))

            assert.equal(input.position, 'absolute')
            assert.notEqual(input.display, 'none')
            assert.notEqual(input.visibility, 'hidden')
        })

        /** The mock-up draws « 15 ML » alone; the count still reaches a screen reader through `aria-describedby`. */
        it('takes the count out of sight without taking it out of the description', () => {
            const count = computed(find(pills(), Contract.selector('count')))

            assert.equal(count.position, 'absolute')
            assert.equal(count.clipPath, 'inset(50%)')
            assert.notEqual(count.display, 'none')
            assert.notEqual(count.visibility, 'hidden')
        })

        it('tells a checked value apart from the others', () => {
            const [checked, other] = pills().querySelectorAll(`${Contract.selector('facet-value')} label`)

            assert.ok(checked && other)
            find(checked, 'input').setAttribute('checked', '')

            assert.equal(computed(checked).borderTopColor, 'currentcolor')
            assert.notEqual(computed(other).borderTopColor, 'currentcolor')
        })

        it('leaves the facets that are not pills as they were', () => {
            pills()
            const control = root.querySelectorAll(Contract.selector('facet'))[1]

            assert.ok(control)
            assert.notEqual(computed(find(control, Contract.selector('input'))).clipPath, 'inset(50%)')
            assert.notEqual(computed(find(control, Contract.selector('count'))).clipPath, 'inset(50%)')
        })
    })

    it('takes the label out of sight without taking it out of the accessible name', () => {
        const label = window.getComputedStyle(find(root, '[data-meili="sort"] > label'))

        assert.equal(label.position, 'absolute')
        assert.notEqual(label.display, 'none')
        assert.notEqual(label.visibility, 'hidden')
    })
})
