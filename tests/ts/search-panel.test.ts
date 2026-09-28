import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { SearchPanel } from '../../resources/assets/ts/site-search/search-panel.ts'
import { click, find } from './dom.ts'
import { openSearch } from './site-search-fixtures.ts'

const panel = () => {
    const { window, root } = openSearch()
    const element = (hook: string) => find(root, Contract.selector(hook))
    const calls: { open: boolean, focused: boolean }[] = []
    const bound = new SearchPanel(new Contract(root), () => calls.push({
        open: !element('search-panel').hidden,
        focused: window.document.activeElement === element('search-input'),
    })).start()

    return { window, bound, element, calls }
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
})
