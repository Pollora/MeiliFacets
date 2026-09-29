import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { SearchPanel } from '../../resources/assets/ts/site-search/search-panel.ts'
import { click, clickFromKeyboard, find, press } from './dom.ts'
import { openSearch } from './site-search-fixtures.ts'

const panel = () => {
    const { window, root } = openSearch()
    const element = (hook: string) => find(root, Contract.selector(hook))
    const calls: { open: boolean, focused: boolean }[] = []
    const bound = new SearchPanel(new Contract(root), () => calls.push({
        open: !element('search-panel').hidden,
        focused: window.document.activeElement === element('search-input'),
    })).start()

    /** Records, for each style flush of the root, whether the panel was open and the transitions cut. */
    const watchFlushes = () => {
        const flushes: { open: boolean, cut: boolean }[] = []

        root.getAnimations = () => {
            flushes.push({ open: !element('search-panel').hidden, cut: root.hasAttribute('data-instant') })

            return []
        }

        return flushes
    }

    const leaveFor = (next: Element) => element('search-input').dispatchEvent(
        new window.FocusEvent('focusout', { bubbles: true, relatedTarget: next })
    )

    return { window, root, bound, element, calls, leaveFor, watchFlushes }
}

describe('SearchPanel', () => {
    it('opens the panel and puts the focus in the field before it calls for the client', () => {
        const { window, element, calls } = panel()

        click(window, element('search-toggle'))

        assert.equal(element('search-toggle').getAttribute('aria-expanded'), 'true')
        assert.deepEqual(calls, [{ open: true, focused: true }])
    })

    it('calls for the client at the first hover or focus of the magnifier, once', () => {
        const { window, element, calls } = panel()
        const toggle = element('search-toggle')

        toggle.dispatchEvent(new window.PointerEvent('pointerenter'))
        toggle.dispatchEvent(new window.FocusEvent('focus'))
        click(window, toggle)

        assert.deepEqual(calls, [{ open: false, focused: false }])
        assert.equal(element('search-panel').hidden, false)
    })

    it('calls for the client when the field is reached without the magnifier', () => {
        const { window, element, calls } = panel()

        element('search-input').dispatchEvent(new window.FocusEvent('focus'))

        assert.equal(calls.length, 1)
    })

    it('closes on a second press of the magnifier', () => {
        const { window, element } = panel()

        click(window, element('search-toggle'))
        click(window, element('search-toggle'))

        assert.equal(element('search-panel').hidden, true)
        assert.equal(element('search-toggle').getAttribute('aria-expanded'), 'false')
    })

    it('says the search is unavailable when the client never arrives', () => {
        const { bound, element } = panel()

        bound.unavailable()

        assert.equal(element('search-unavailable').hidden, false)
    })

    it('closes on Escape, keeps what was typed and hands the focus back to the magnifier', () => {
        const { window, element } = panel()
        const input = element('search-input') as HTMLInputElement

        click(window, element('search-toggle'))
        input.value = 'ser'
        press(window, input, 'Escape')

        assert.equal(element('search-panel').hidden, true)
        assert.equal(element('search-toggle').getAttribute('aria-expanded'), 'false')
        assert.equal(window.document.activeElement === element('search-toggle'), true)
        assert.equal(input.value, 'ser')
    })

    it('closes on a click outside the magnifier and the panel', () => {
        const { window, element } = panel()

        click(window, element('search-toggle'))
        click(window, element('search-input'))
        assert.equal(element('search-panel').hidden, false)

        click(window, find(window.document, '#elsewhere'))
        assert.equal(element('search-panel').hidden, true)
    })

    it('closes once Tab takes the focus out of the panel, not while it stays with the magnifier', () => {
        const { window, element, leaveFor } = panel()

        click(window, element('search-toggle'))
        leaveFor(element('search-toggle'))
        assert.equal(element('search-panel').hidden, false)

        leaveFor(find(window.document, '#elsewhere'))
        assert.equal(element('search-panel').hidden, true)
    })

    it('writes the room left under its top edge while open, and takes it back once closed', () => {
        const { window, element } = panel()
        const room = () => element('search-panel').style.getPropertyValue('--meili-search-room')

        window.happyDOM.setViewport({ width: 393, height: 800 })
        element('search-panel').getBoundingClientRect = () => ({ top: 72 }) as DOMRect
        click(window, element('search-toggle'))
        assert.equal(room(), '728px')

        window.happyDOM.setViewport({ width: 393, height: 600 })
        window.dispatchEvent(new window.Event('resize'))
        assert.equal(room(), '528px')

        click(window, element('search-toggle'))
        assert.equal(room(), '')
    })

    it('opens and closes from the keyboard at once: the change is flushed with the transitions cut', () => {
        const { window, root, element, watchFlushes } = panel()
        const flushes = watchFlushes()

        clickFromKeyboard(window, element('search-toggle'))
        clickFromKeyboard(window, element('search-toggle'))

        assert.deepEqual(flushes, [{ open: true, cut: true }, { open: false, cut: true }])
        assert.equal(root.hasAttribute('data-instant'), false)
    })

    it('leaves the pointer its motion, in and out', () => {
        const { window, root, element, watchFlushes } = panel()
        const flushes = watchFlushes()

        click(window, element('search-toggle'))
        click(window, element('search-toggle'))
        click(window, element('search-toggle'))
        click(window, find(window.document, '#elsewhere'))

        assert.deepEqual(flushes, [])
        assert.equal(root.hasAttribute('data-instant'), false)
    })

    it('closes at once on Escape and when Tab leaves it', () => {
        const { window, element, leaveFor, watchFlushes } = panel()
        const flushes = watchFlushes()

        click(window, element('search-toggle'))
        press(window, element('search-input'), 'Escape')
        click(window, element('search-toggle'))
        leaveFor(find(window.document, '#elsewhere'))

        assert.deepEqual(flushes, [{ open: false, cut: true }, { open: false, cut: true }])
    })

    it('measures the room from the anchor, not from a box its entrance still offsets', () => {
        const { window, element } = panel()
        const anchor = window.document.body
        const room = () => element('search-panel').style.getPropertyValue('--meili-search-room')

        window.happyDOM.setViewport({ width: 1440, height: 806 })
        anchor.getBoundingClientRect = () => ({ top: 0 }) as DOMRect
        Object.defineProperty(element('search-panel'), 'offsetParent', { value: anchor })
        Object.defineProperty(element('search-panel'), 'offsetTop', { value: 80 })
        element('search-panel').getBoundingClientRect = () => ({ top: 72 }) as DOMRect
        click(window, element('search-toggle'))

        assert.equal(room(), '726px')
    })
})
