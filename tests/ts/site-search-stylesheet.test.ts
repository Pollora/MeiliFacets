import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { SEARCH_STYLESHEET, find } from './dom.ts'
import { openSearch } from './site-search-fixtures.ts'

const styled = (width: number) => {
    const { window, root } = openSearch(undefined, { styled: true })
    const element = (hook: string) => find(root, Contract.selector(hook))

    window.happyDOM.setViewport({ width, height: 800 })

    return { window, element, style: (hook: string) => window.getComputedStyle(element(hook)) }
}

describe('the site search stylesheet', () => {
    it('hangs the panel under the positioned element the theme anchors it to, across its whole width', () => {
        const { style } = styled(393)

        assert.deepEqual(
            [style('search-panel').position, style('search-panel').top, style('search-panel').left, style('search-panel').right],
            ['absolute', '100%', '0px', '0px'],
        )
    })

    it('scrolls inside the panel, within the height the client measured', () => {
        const { element, style } = styled(393)

        element('search-panel').style.setProperty('--meili-search-available-height', '640px')

        assert.equal(style('search-panel').overflowY, 'auto')
        assert.equal(style('search-panel').height, '640px')
    })

    it('fills the available height on a small screen and only caps the panel from 48em', () => {
        const { element, style } = styled(1440)

        element('search-panel').style.setProperty('--meili-search-available-height', '640px')

        assert.equal(style('search-panel').height, 'auto')
        assert.equal(style('search-panel').maxHeight, '640px')
    })

    it('keeps the sections under the field while the panel is taller than they need, as when its height eases up', () => {
        const { style } = styled(1440)

        assert.equal(style('search-panel').alignContent, 'flex-start')
    })

    /** Read as written: happy-dom does not evaluate `(scripting: enabled)`. */
    it('locks the page behind an open panel on a small screen only, read from the mark the root carries', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')
        const lock = /:root:has\(\[data-meili="search"\]\[data-open\]\) \{\s*overflow: hidden;/
        const small = source.slice(source.indexOf('@media (scripting: enabled) and (width < 48em)'), source.indexOf('@media (width >= 48em)'))

        assert.match(small, lock)
        assert.equal(source.match(new RegExp(lock, 'g'))?.length, 1)
    })

    /** iOS zooms the page into a field under 16px on focus: the floor holds on touch screens only. */
    it('sizes the field like the panel, and never under 16px on a touch screen', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')
        const coarse = source.slice(source.indexOf('@media (pointer: coarse)'))

        assert.match(source, /\[data-meili="search-input"\] \{[^}]*font-size: var\(--meili-ui\);/)
        assert.match(source, /--meili-field-font-min: 1rem;/)
        assert.match(coarse.slice(0, coarse.indexOf('\n}')), /\[data-meili="search-input"\] \{\s*font-size: max\(var\(--meili-field-font-min\), var\(--meili-ui\)\);/)
    })

    /** A fixed header spans the viewport: without the gutter it widens by the scrollbar the lock removes. */
    it('keeps the room of the scrollbar it removes, so the page underneath keeps its width', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')
        const small = source.slice(source.indexOf('@media (scripting: enabled) and (width < 48em)'), source.indexOf('@media (width >= 48em)'))

        assert.match(small, /:root:has\(\[data-meili="search"\]\[data-open\]\) \{\s*overflow: hidden;\s*scrollbar-gutter: stable;\s*\}/)
    })

    it('holds the field at the top of the panel while the results scroll under it', () => {
        const { style } = styled(393)

        assert.equal(style('search-field').position, 'sticky')
        assert.match(style('search-field').top, /-1 \* 24px/)
    })

    it('sets the link to the archive on the row of the heading, above the options', () => {
        const { style } = styled(1440)

        assert.deepEqual([style('search-see-all').gridRow, style('search-see-all').gridColumn], ['1', '2'])
        assert.equal(style('search-results').gridColumn, '1 / -1')
    })

    /** Read as written: happy-dom draws no pseudo-element. */
    it('dims the page under the anchor of an open panel from 48em only, leaving the header above it, with no filter', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')
        const wide = source.slice(source.indexOf('@media (width >= 48em)'))
        const scrim = /\[data-meili="search"\]::after \{[^}]*position: absolute;[^}]*top: 100%;[^}]*display: none;/
        const shown = /\[data-meili="search"\]\[data-open\]::after \{[^}]*display: block;/

        assert.match(wide, scrim)
        assert.match(wide, shown)
        assert.doesNotMatch(wide, /\[data-meili="search"\]:has\(/, 'a :has() over the root costs a style recalculation of its subtree at every opening')
        assert.equal(source.match(new RegExp(scrim, 'g'))?.length, 1)
        assert.doesNotMatch(source, /filter\s*:/)
    })

    /** Read as written: happy-dom runs no transition. */
    it('animates transform and opacity only, besides the colour of a hovered row, and casts no shadow under the panel', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')
        const animated = [...source.matchAll(/transition(?:-property)?:\s*([^;]+);/g)]
            .flatMap(([, value = '']) => value.split(','))
            .map((transition) => transition.trim().split(/\s+/)[0])

        assert.deepEqual([...new Set(animated)].sort(), ['background-color', 'display', 'none', 'opacity', 'transform'])
        assert.doesNotMatch(source.slice(source.indexOf('[data-meili="search-panel"] {'), source.indexOf('[data-meili="search-panel"][hidden]')), /box-shadow/)
    })

    it('cuts every transition of the panel and the scrim while the client marks a change instant', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')
        const instant = source.slice(source.indexOf('[data-meili="search"][data-instant]'))

        assert.match(instant, /\[data-meili="search"\]\[data-instant\] \[data-meili="search-panel"\],\s*\[data-meili="search"\]\[data-instant\]::after \{\s*transition: none;/)
        assert.ok(source.indexOf('[data-meili="search"][data-instant]') > source.indexOf('@media (prefers-reduced-motion: reduce)'), 'after the reduced-motion rules')
    })

    it('slides the panel only without a reduced-motion preference: a fade is all that is left', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')
        const reduced = source.slice(source.indexOf('@media (prefers-reduced-motion: reduce)'), source.indexOf('[data-meili="search"][data-instant]'))

        assert.match(reduced, /\[data-meili="search-panel"\]\[hidden\],\s*\[data-meili="search-toggle"\]:active \{\s*transform: none;/)
        assert.match(reduced, /@starting-style \{\s*\[data-meili="search-panel"\] \{\s*transform: none;/)
    })

    /** Read as written: happy-dom resolves no logical margin. */
    it('keeps the thumbnail on the edge of the rule: the row gives back its own padding', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')

        assert.match(source, /\[data-meili="search-results"\] > \[data-meili="card"\] \{\s*margin-inline: calc\(-1 \* var\(--meili-search-row\)\);/)
        assert.match(source, /\[data-meili="search-results"\] \[data-meili="url"\] \{[^}]*padding: var\(--meili-search-row\);/)
    })

    it('keeps the live region out of sight but in the tree', () => {
        const { style } = styled(393)

        assert.deepEqual([style('search-status').position, style('search-status').width], ['absolute', '1px'])
    })

    it('draws a result as a row: its thumbnail, then its words', () => {
        const { window, element, style } = styled(393)
        const results = element('search-results')
        const template = find<HTMLTemplateElement>(element('search-section'), Contract.selector('search-card-template'))

        results.append(template.content.cloneNode(true))

        assert.equal(window.getComputedStyle(find(results, Contract.selector('url'))).display, 'grid')
        assert.equal(style('search-results').listStyle, 'none')
    })

    /** Read as written: happy-dom draws no pseudo-element and runs no animation. */
    it('draws the bar of a slow search under the field only while the panel is busy, past the busy delay, by transform alone', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')
        const bar = /\[data-meili="search-panel"\]\[aria-busy="true"\] > \[data-meili="search-field"\]::after \{([^}]*)\}/g
        const [shown, reduced] = [...source.matchAll(bar)].map(([, rule = '']) => rule)
        const sweep = source.slice(source.indexOf('@keyframes meili-search-busy {'), source.indexOf('@keyframes meili-search-busy-shown'))

        assert.match(shown ?? '', /content: "";[^]*transform: scaleX\(0\);[^]*animation: meili-search-busy var\(--meili-duration-bar\) linear infinite;\s*animation-delay: var\(--meili-duration-busy-delay\);/)
        assert.match(source, /--meili-duration-busy-delay: 150ms;/)
        assert.deepEqual([...sweep.matchAll(/^\s+([a-z-]+):/gm)].map(([, property]) => property).filter((property, rank, all) => all.indexOf(property) === rank), ['transform'])
        assert.match(reduced ?? '', /transform: none;[^]*animation: meili-search-busy-shown/)
        assert.equal(source.match(/::after \{\s*content: "";/g)?.length, 2)
    })

    it('takes a node that leaves out of the flow, and out of the rule that spaces the field from the first section', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')

        assert.match(source, /\[data-meili="search"\] \[data-leaving\] \{\s*position: absolute;\s*box-sizing: border-box;\s*margin: 0;\s*overflow: hidden;\s*pointer-events: none;/)
        assert.match(source, /:has\(> \[data-meili="search-section"\]:not\(\[hidden\], \[data-leaving\]\)\)/)
    })

    it('marks the row the arrows reached by its tint alone, and lets its count move', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')

        assert.match(source, /\[data-meili="search-results"\] > \[data-meili="card"\]\[data-active\] \{\s*background: var\(--meili-tint-active\);\s*\}/)
        assert.match(source, /\[data-meili="search-count"\] \{\s*display: inline-block;/)
    })

    it('paints the slow bar in the text colour under forced colours, where a background would vanish', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')
        const forced = source.slice(source.indexOf('@media (forced-colors: active)'))

        assert.match(forced.slice(0, forced.indexOf('\n}')), /\[data-meili="search-panel"\]\[aria-busy="true"\] > \[data-meili="search-field"\]::after,[^{]*\{\s*background: CanvasText;\s*forced-color-adjust: none;/)
    })

    it('gives the link to the archive the height of a touch target, its text left as it is', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')
        const seeAll = source.slice(source.indexOf('[data-meili="search-see-all"] {'))

        assert.match(seeAll.slice(0, seeAll.indexOf('}')), /display: inline-flex;\s*align-items: center;[^}]*min-height: var\(--meili-control-min\);[^}]*font-size: var\(--meili-small\);/)
        assert.match(source, /@media \(pointer: coarse\) \{\s*\[data-meili="search"\] \{\s*--meili-control-min: 2\.75rem;/)
    })

    it('lets the panel and the scrim leave on their own curve, the entrance untouched', () => {
        const source = readFileSync(SEARCH_STYLESHEET, 'utf8')

        assert.match(source, /--meili-ease-exit: ease;/)
        assert.match(source, /\[data-meili="search-panel"\]\[hidden\] \{[^}]*transition-timing-function: var\(--meili-ease-exit\);/)
        assert.match(source, /opacity var\(--meili-duration-pop-out\) var\(--meili-ease-exit\),/)
        assert.match(source, /transition-duration: var\(--meili-duration-pop-in\);\s*transition-timing-function: var\(--meili-ease\);/)
    })
})
