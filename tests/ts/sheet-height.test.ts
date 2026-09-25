import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { SheetHeight } from '../../resources/assets/ts/drawer/sheet-height.ts'
import { find, load } from './dom.ts'

import type { TestWindow } from './dom.ts'

const SHEET = `
<div id="sheet">
    <div id="head"></div>
    <div id="handle" style="position: absolute"></div>
    <div id="body"><div id="facets"></div></div>
    <div id="foot"></div>
</div>`

describe('SheetHeight', () => {
    let window: TestWindow
    let observed: Element[]

    const sized = (id: string, offsetHeight: number, scrollHeight = offsetHeight) => {
        const node = find(window.document, `#${id}`)
        Object.defineProperty(node, 'offsetHeight', { value: offsetHeight, configurable: true })
        Object.defineProperty(node, 'scrollHeight', { value: scrollHeight, configurable: true })
    }
    const sheet = () => find(window.document, '#sheet')

    beforeEach(() => {
        window = load(SHEET)
        observed = []
        window.ResizeObserver = class {
            observe(node: Element) { observed.push(node) }
            disconnect() { observed = [] }
            unobserve() {}
        }
        sized('head', 73)
        sized('handle', 24)
        sized('body', 300, 520)
        sized('foot', 81)
    })

    /** The body scrolls: its content, not its box, is what the sheet asks room for. */
    it('asks the height its parts in flow need, the body counted by its content', () => {
        new SheetHeight(sheet()).follow()

        assert.equal(sheet().style.height, `${73 + 520 + 81}px`)
    })

    it('watches its parts and what they hold, and stops', () => {
        const height = new SheetHeight(sheet())

        height.follow()
        assert.ok(observed.includes(find(window.document, '#facets')))
        assert.equal(observed.includes(find(window.document, '#handle')), false)

        height.stop()
        assert.deepEqual(observed, [])
    })

    it('gives the sheet its own height back when released', () => {
        const height = new SheetHeight(sheet())

        height.follow()
        height.release()

        assert.equal(sheet().style.height, '')
    })
})
