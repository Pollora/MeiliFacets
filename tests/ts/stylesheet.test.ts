import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
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
        find(root, 'legend').insertAdjacentHTML('afterbegin', '<button data-meili="toggle">Brand</button>')
        const commands = ['page', 'previous', 'next', 'reset', 'apply', 'sort-trigger', 'sort-option', 'input', 'more', 'toggle', 'active-value']

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
     * R-168: the fold button reads as one more row of values, at the scale of the other commands,
     * set apart from the list by the same gap that sets the list apart from its legend.
     * Spacing is compared as declared: happy-dom resolves `em` against the wrong size inside the label.
     */
    it('draws the fold button as a row of values rather than a native button', () => {
        const rules = [...window.document.styleSheets].flatMap((sheet) => [...sheet.cssRules])
        const declared = (selector: string) => {
            const styled = rules.filter((candidate) => candidate instanceof window.CSSStyleRule)
            const rule = styled.find((candidate) => candidate.selectorText === selector)
                ?? styled.find((candidate) => candidate.selectorText.split(',').map((part) => part.trim()).includes(selector))

            assert.ok(rule instanceof window.CSSStyleRule, selector)

            return rule.style
        }
        const more = declared(Contract.selector('more'))
        const row = declared(`${Contract.selector('facet-value')} label`)
        const list = declared(`${Contract.selector('facet')} ul`)

        assert.equal(style('more').fontSize, style('reset').fontSize)
        assert.equal(style('more').fontFamily, style('reset').fontFamily)
        assert.equal(style('more').borderTopWidth, '0px')
        assert.equal(style('more').backgroundColor, 'transparent')
        assert.equal(more.marginLeft, row.marginLeft)
        assert.equal(more.padding, row.padding)
        assert.equal(more.marginTop, list.marginTop)
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

    describe('a collapsible facet', () => {
        beforeEach(() => {
            ({ window, root } = open(listingMarkup({ collapsible: true }), { styled: true }))
        })

        const facet = () => find(root, Contract.selector('facet'))
        const panel = () => find(root, Contract.selector('panel'))

        it('sits in line with its sisters and hangs its panel under its trigger, over the page', () => {
            assert.equal(window.getComputedStyle(find(root, Contract.selector('facets'))).display, 'flex')
            assert.equal(window.getComputedStyle(facet()).flexGrow, '0')
            assert.equal(window.getComputedStyle(facet()).position, 'relative')
            assert.equal(style('panel').position, 'absolute')
            assert.equal(style('panel').top, '100%')
            assert.equal(style('panel').left, '0px')
        })

        it('draws its trigger as a pill at the height and the scale of the other commands', () => {
            assert.equal(style('toggle').minHeight, style('apply').minHeight)
            assert.equal(style('toggle').fontSize, style('apply').fontSize)
            assert.equal(style('toggle').borderTopLeftRadius, '999px')
        })

        it('hangs a panel aligned on the end from the right edge of its trigger', () => {
            panel().setAttribute('data-align-end', '')

            assert.equal(style('panel').left, 'auto')
            assert.equal(style('panel').right, '0px')
        })

        /** Mobile first: under the threshold, the same markup is a full-width section with its panel in line. */
        it('becomes a full-width section with its panel in line on a narrow screen', () => {
            window.happyDOM.setViewport({ width: 390, height: 800 })

            assert.equal(window.getComputedStyle(facet()).flexBasis, '100%')
            assert.notEqual(style('panel').position, 'absolute')
            assert.equal(style('toggle').borderTopWidth, '0px')
        })

        it('leaves the facets that do not collapse as they were', () => {
            const plain = find(root, '[data-taxonomy="pa_size"]')

            assert.equal(window.getComputedStyle(plain).display, 'block')
            assert.notEqual(window.getComputedStyle(plain).position, 'relative')
        })
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

        /** Read as written: happy-dom resolves no system colour. The theme's ink is the theme's (R-173). */
        it('tells a checked value apart in the bar in the colours of the page, inverted', () => {
            const source = readFileSync(new URL('../../resources/assets/css/meilifacets.css', import.meta.url), 'utf8')
            const checked = declared(source, `${Contract.selector('facet')}[data-presentation="pill"]:has(${Contract.selector('toggle')}) ${Contract.selector('facet-value')} label:has(:checked)`)

            assert.match(checked, /border-color: CanvasText;/)
            assert.match(checked, /background: CanvasText;/)
            assert.match(checked, /color: Canvas;/)
        })

        /** The column keeps the design it had before the bar (25a5aa3): a tint, not an inversion. */
        it('tells a checked value apart out of the bar by a tint and its border, as before', () => {
            const source = readFileSync(new URL('../../resources/assets/css/meilifacets.css', import.meta.url), 'utf8')
            const pill = `${Contract.selector('facet')}[data-presentation="pill"] ${Contract.selector('facet-value')} label`

            assert.match(declared(source, `${pill}:active,\n${pill}:has(:checked)`), /background: var\(--meili-press\);/)
            assert.match(declared(source, `\n\n${pill}:has(:checked)`), /\{\s*border-color: currentColor;\s*$/)
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

/** The declarations of the first rule written for exactly this selector list, in a slice of the sheet. */
const declared = (source: string, selector: string) => {
    const at = source.indexOf(`${selector} {`)

    assert.notEqual(at, -1, selector)

    return source.slice(at, source.indexOf('}', at))
}

/** Read as written: happy-dom evaluates neither `max()` nor `(pointer: coarse)`. */
describe('the height of the controls', () => {
    const source = readFileSync(new URL('../../resources/assets/css/meilifacets.css', import.meta.url), 'utf8')
    const HEIGHT = 'max(var(--meili-control), var(--meili-control-min))'

    it('reads the control height through the touch floor everywhere', () => {
        const uses = source.split('var(--meili-control)').length - 1

        assert.ok(uses > 0)
        assert.equal(source.split(HEIGHT).length - 1, uses)
    })

    /** The coarse pointer raises a floor, it never redefines the height a theme chose. */
    it('lets the coarse pointer raise a floor and nothing else', () => {
        const coarse = source.slice(source.indexOf('@media (pointer: coarse)'))

        assert.match(coarse, /--meili-control-min: 2\.75rem/)
        assert.doesNotMatch(coarse.slice(0, coarse.indexOf('}')), /--meili-control:/)
        assert.match(source, /--meili-control: 3rem;/)
    })

    /** « Clear all » in words beside the active values of the bar: the same pill, asked for (Louis, 2026-09-25). */
    it('pads the pill-shaped controls of the bar with one inline measure', () => {
        const { window, root } = open(listingMarkup({ collapsible: true }), { styled: true })
        find(root, Contract.selector('reset')).setAttribute('data-shape', 'pill')
        const pads = ['toggle', 'reset'].map((hook) => window.getComputedStyle(find(root, Contract.selector(hook))).paddingLeft)

        assert.deepEqual(pads, ['24px', '24px'])
        assert.equal(window.getComputedStyle(find(root, Contract.selector('reset'))).borderRadius, '999px')
    })

    /** Without a variant, the column is drawn as it was at 25a5aa3, the height of the controls aside: `0.25em` and `0.85em` at 14px. */
    it('keeps the design of the column for the controls without a variant', () => {
        const { window, root } = open(listingMarkup(), { styled: true })
        find(root, Contract.selector('active-values')).insertAdjacentHTML('afterbegin', '<li><button data-meili="active-value">Acme</button></li>')
        const computed = (hook: string) => window.getComputedStyle(find(root, Contract.selector(hook)))

        assert.equal(computed('reset').borderRadius, '3.5px')
        assert.equal(computed('reset').paddingLeft, '11.9px')
        assert.equal(computed('sort-trigger').paddingLeft, '11.9px')
        assert.equal(computed('active-value').paddingLeft, '11.9px')
        assert.equal(computed('active-value').gap, '0.5em')
    })
})

/** Read as written: happy-dom evaluates neither `(scripting: enabled)` nor `@starting-style`. */
describe('the drawer as a sheet', () => {
    const source = readFileSync(new URL('../../resources/assets/css/meilifacets.css', import.meta.url), 'utf8')
    const sheet = source.slice(source.indexOf('@media (scripting: enabled) and (width < 48em)'))
    const DRAWER = Contract.selector('drawer')

    /** Figma 17:754: 24 × 32 head, 40 around the body, 32 at the sides, 16 × 32 foot. */
    it('spaces its parts with the mock-up\'s measures, through variables a theme can move', () => {
        assert.match(source, /--meili-drawer-gutter: 2rem;/)
        assert.match(source, /--meili-drawer-block: 2\.5rem;/)
        assert.match(declared(sheet, '.meilifacetsDrawerHead'), /padding: var\(--meili-section-gap\) var\(--meili-drawer-gutter\);/)
        assert.match(declared(sheet, '.meilifacetsDrawerBody'), /padding: var\(--meili-drawer-block\) var\(--meili-drawer-gutter\);/)
        assert.match(declared(sheet, '.meilifacetsDrawerFooter'), /padding: var\(--meili-section-step\) var\(--meili-drawer-gutter\)/)
    })

    /** At rest nothing may move: a transition there plays on the first render and on every crossing of the threshold. */
    it('holds no transition while closed at rest, and plays its way out only while it closes', () => {
        for (const selector of [DRAWER, `${DRAWER}::before`, '.meilifacetsDrawerSheet']) {
            assert.doesNotMatch(declared(sheet, selector), /transition/, selector)
        }

        assert.match(declared(sheet, `${DRAWER}[data-closing]`), /transition: visibility 0s linear var\(--meili-duration-drawer-out\);/)
        assert.match(declared(sheet, `${DRAWER}[data-closing] .meilifacetsDrawerSheet`), /transform var\(--meili-duration-drawer-out\)/)
    })

    /** R-173 (3): one layout property moves — the width of « Apply » — and only in an open drawer. */
    it('lets « Apply » take the room the bin leaves, by its width alone', () => {
        const footer = '.meilifacetsDrawerFooter'
        const bin = `${Contract.selector('reset')}[data-shape="icon"]`
        const open = `${DRAWER}[aria-modal="true"] ${footer} > `

        assert.match(declared(sheet, `${footer} > ${bin}`), /position: absolute;/)
        assert.match(declared(sheet, `${footer}:has(> ${bin}:not([hidden])) > ${Contract.selector('apply')}`), /width: calc\(/)
        assert.match(declared(sheet, `${open}${Contract.selector('apply')}`), /width var\(--meili-duration-resize\) var\(--meili-ease-resize\)/)
        assert.match(declared(source, '[data-listing]'), /--meili-duration-pop-in: 200ms;/)
        assert.match(declared(source, '[data-listing]'), /--meili-duration-pop-out: 150ms;/)
        assert.match(declared(sheet, `${open}${bin}`), /display var\(--meili-duration-pop-in\) allow-discrete/)
        assert.match(declared(sheet, `${open}${bin}[hidden]`), /transition-duration: var\(--meili-duration-pop-out\);/)
        assert.match(declared(sheet, `${open}${bin}[hidden]`), /interactivity: inert;/)
    })

    /** In `immediate`, « Apply » only marks the way out of the sheet: gone everywhere else, JavaScript off included. */
    it('shows a sheet-only « Apply » in the sheet and nowhere else', () => {
        const apply = `${Contract.selector('apply')}[data-only="sheet"]`

        assert.match(declared(source, apply), /display: none;/)
        assert.match(declared(sheet, `${DRAWER} ${apply}`), /display: inline-flex;/)
    })
})

/** Read as written: happy-dom evaluates neither `(hover: hover)` nor `transition` lists. */
describe('the duration of a hover', () => {
    const source = readFileSync(new URL('../../resources/assets/css/meilifacets.css', import.meta.url), 'utf8')
    const HOVER = 'var(--meili-duration-hover) var(--meili-ease)'
    const COLOURS = ['color', 'background-color', 'border-color']
    const rules = [...source.matchAll(/([^{}]+)\{([^{}]*)\}/g)].map(([, selector = '', body = '']) => ({
        selectors: selector.trim().split(/,\s*/),
        body,
    }))
    const transitions = (body: string) => [...body.matchAll(/transition:([^;]+);/g)]
        .flatMap(([, list = '']) => list.split(/,(?![^(]*\))/).map((item) => item.trim()))
    const propertyOf = (item: string) => item.split(' ')[0] ?? ''
    const hovered = rules.filter(({ selectors }) => selectors.some((selector) => selector.includes(':hover')))
    const changes = (body: string) => [...body.matchAll(/^\s*([a-z-]+):/gm)].map(([, property = '']) => (property === 'background' ? 'background-color' : property))

    it('is one variable, at the hover length of the verdict', () => {
        assert.match(declared(source, '[data-listing]'), /--meili-duration-hover: 150ms;/)
    })

    /** Cohesion: a tint that fades at 120 ms beside one at 150 ms reads as two interfaces (Emil, 2026-09-25). */
    it('times every colour a hover changes through that variable', () => {
        const colourItems = rules.flatMap(({ body }) => transitions(body))
            .filter((item) => COLOURS.includes(propertyOf(item)))

        assert.ok(hovered.every(({ body }) => changes(body).every((property) => COLOURS.includes(property) || ['transform', 'opacity'].includes(property))))
        assert.ok(colourItems.length > 0)
        assert.deepEqual(colourItems.filter((item) => !item.endsWith(HOVER)), [])
    })

    /** Only a hover that moves or reveals is timed by what it hovers: the press keeps its own length. */
    it('times what a hover moves or reveals through that variable', () => {
        const subjects = hovered
            .filter(({ body }) => changes(body).some((property) => ['transform', 'opacity'].includes(property)))
            .flatMap(({ selectors, body }) => selectors.map((selector) => ({
                subject: selector.replace(':hover', '').split(' ').at(-1) ?? '',
                moved: changes(body).filter((property) => ['transform', 'opacity'].includes(property)),
            })))

        assert.ok(subjects.length > 0)

        for (const { subject, moved } of subjects) {
            const timed = rules.filter(({ selectors, body }) => selectors.includes(subject) && body.includes('transition:'))
                .flatMap(({ body }) => transitions(body))
                .filter((item) => moved.includes(propertyOf(item)))

            assert.ok(timed.length > 0, subject)
            assert.deepEqual(timed.filter((item) => !item.endsWith(HOVER)), [], subject)
        }
    })
})

/** Read as written: happy-dom evaluates neither `prefers-reduced-motion` nor `:focus-visible`. R-176. */
describe('the motion of the panels and the drawer', () => {
    const source = readFileSync(new URL('../../resources/assets/css/meilifacets.css', import.meta.url), 'utf8')
    const PANEL = Contract.selector('panel')
    const desktop = source.slice(source.indexOf('@media (width >= 48em)'))
    const reduced = source.slice(source.indexOf(`@media (prefers-reduced-motion: reduce) {\n    ${PANEL}`))

    it('pops a floating panel in over 180 ms and out over 120, through variables a theme can move', () => {
        assert.match(declared(source, '[data-listing]'), /--meili-duration-panel-in: 180ms;/)
        assert.match(declared(source, '[data-listing]'), /--meili-duration-panel-out: 120ms;/)
        assert.match(desktop, /\[data-meili="panel"\] \{[^}]*opacity var\(--meili-duration-panel-out\)/)
    })

    /** ANIM-2: a length written in a rule is a length no theme can move. */
    it('writes every length on a variable of the listing', () => {
        const written = source.split('\n').filter((line) => /\b[1-9]\d*m?s\b/.test(line) && !/^\s*--meili-duration-[a-z-]+: /.test(line))

        assert.deepEqual(written, [])
    })

    it('cuts the transition of a panel the keyboard closes or a pill hands over', () => {
        assert.match(declared(source, `${PANEL}[data-instant][hidden]`), /transition: none;/)
        assert.ok(source.indexOf(`${PANEL}[data-instant]`) > source.length - reduced.length, 'after the reduced-motion rules, which it must beat')
    })

    /** Emil: a press animates, a key does not — and a button left shrunk under the focus reads as stuck. */
    it('moves the close button under a press only, never under the focus', () => {
        assert.doesNotMatch(source, /drawer-close"\]:focus-visible \{[^}]*transform/)
        assert.match(declared(source, `button${Contract.selector('drawer-close')}:active`), /transform: scale\(0\.75\);/)
    })

    it('fades a section out under reduced motion over the fade length', () => {
        assert.match(declared(reduced, `${PANEL}[hidden]`), /opacity var\(--meili-duration-fade\)/)
        assert.doesNotMatch(declared(reduced, `${PANEL}[hidden]`), /transform var/)
    })

    it('keeps a short fade on the sort list under reduced motion', () => {
        assert.match(declared(source, '[data-listing]'), /--meili-duration-list: 160ms;/)
        assert.match(declared(reduced, `${Contract.selector('sort-list')}[hidden]`), /opacity var\(--meili-duration-list\)/)
        assert.doesNotMatch(declared(reduced, `${Contract.selector('sort-list')}[hidden]`), /opacity: 1;/)
    })
})
