import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { LightDismiss } from '../../resources/assets/ts/shared/light-dismiss.ts'
import { click, find, load, press } from './dom.ts'

const MARKUP = `
<div id="root">
    <button id="toggle">Open</button>
    <div id="panel"><input id="inside"></div>
</div>
<button id="outside">Elsewhere</button>`

const dismissal = () => {
    const window = load(MARKUP)
    const element = (id: string) => find(window.document, `#${id}`)
    const closed: string[] = []
    let open = true

    new LightDismiss(element('root'), {
        open: () => (open ? [element('toggle')] : []),
        panelOf: () => element('panel'),
        closeInstantly: () => {
            closed.push('instantly')
            open = false
        },
        close: () => {
            closed.push('with its motion')
            open = false
        },
    }).start()

    const leave = (from: string, to: string) => element(from).dispatchEvent(
        new window.FocusEvent('focusout', { bubbles: true, relatedTarget: element(to) })
    )

    return { window, element, closed, leave }
}

describe('LightDismiss', () => {
    it('closes on Escape from inside the panel, consumes it and hands the focus back to the toggle', () => {
        const { window, element, closed } = dismissal()
        const escape = new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true })

        element('inside').dispatchEvent(escape)

        assert.deepEqual(closed, ['instantly'])
        assert.equal(escape.defaultPrevented, true)
        assert.equal(window.document.activeElement?.id, 'toggle')
    })

    it('leaves alone an Escape another component consumed, and any other key', () => {
        const { window, element, closed } = dismissal()
        const consumed = new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true })

        element('inside').addEventListener('keydown', (event) => event.preventDefault())
        element('inside').dispatchEvent(consumed)
        press(window, element('toggle'), 'Enter')

        assert.deepEqual(closed, [])
    })

    it('closes at once when the focus moves past the toggle and the panel', () => {
        const { closed, leave } = dismissal()

        leave('inside', 'toggle')
        assert.deepEqual(closed, [])

        leave('inside', 'outside')
        assert.deepEqual(closed, ['instantly'])
    })

    it('lets a press decide rather than the focus it moves', () => {
        const { window, element, closed, leave } = dismissal()

        element('outside').dispatchEvent(new window.PointerEvent('pointerdown', { bubbles: true }))
        leave('inside', 'outside')

        assert.deepEqual(closed, [])
    })

    it('closes on a click outside, never on one on the toggle or in the panel', () => {
        const { window, element, closed } = dismissal()

        click(window, element('toggle'))
        click(window, element('inside'))
        assert.deepEqual(closed, [])

        click(window, element('outside'))
        assert.deepEqual(closed, ['with its motion'])
    })
})
