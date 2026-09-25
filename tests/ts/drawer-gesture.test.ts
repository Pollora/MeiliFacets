import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { DrawerGesture } from '../../resources/assets/ts/drawer/drawer-gesture.ts'
import { find, load } from './dom.ts'

import type { TestWindow } from './dom.ts'

const SHEET = `
<div id="drawer" data-meili="drawer">
    <div id="sheet" data-meili="drawer-sheet">
        <div id="head"><span id="title">Filter</span><button type="button" id="close" data-meili="drawer-close">✕</button></div>
        <div id="handle" data-meili="drawer-close"></div>
        <div id="body">
            <input id="field"><p id="text">Brand</p>
            <div id="track" data-meili="price-track"><span id="handle-min" data-meili="price-handle"></span></div>
        </div>
    </div>
</div>`

const HEIGHT = 400

describe('DrawerGesture', () => {
    let window: TestWindow
    let open: boolean
    let dismissed: number
    let reduced: { matches: boolean }
    let gesture: DrawerGesture
    let now: number

    const sheet = () => find(window.document, '#sheet')
    const drawer = () => find(window.document, '#drawer')
    /** The gesture's clock is the event's own: every event is stamped with the test's clock. */
    const pointer = (type: string, node: Element, clientY: number, init: PointerEventInit = {}) => {
        const event = new window.PointerEvent(type, { bubbles: true, cancelable: true, pointerId: 1, isPrimary: true, clientY, ...init })

        Object.defineProperty(event, 'timeStamp', { value: now })
        node.dispatchEvent(event)
    }
    const later = (ms: number) => {
        now += ms
    }
    const drag = (from: Element, ...moves: number[]) => {
        pointer('pointerdown', from, 100)
        moves.forEach((y) => pointer('pointermove', from, 100 + y))
    }
    const lift = (y: number) => pointer('pointerup', sheet(), 100 + y)

    beforeEach(() => {
        window = load(SHEET)
        open = true
        dismissed = 0
        now = 1000
        reduced = { matches: false }
        Object.defineProperty(sheet(), 'offsetHeight', { value: HEIGHT })
        gesture = new DrawerGesture(drawer(), { isOpen: () => open, dismiss: () => dismissed++ }, reduced as MediaQueryList).start()
    })

    it('follows the finger one to one while it pulls down', () => {
        drag(find(window.document, '#head'), 10, 30)

        assert.equal(sheet().style.transform, 'translateY(30px)')
        assert.equal(drawer().hasAttribute('data-dragging'), true)
        assert.equal(sheet().hasPointerCapture(1), true)
    })

    it('moves only the part the markup hooks as its sheet', () => {
        sheet().removeAttribute('data-meili')
        drag(find(window.document, '#head'), 10, 30)

        assert.equal(sheet().style.transform, '')
        assert.equal(drawer().hasAttribute('data-dragging'), false)
    })

    it('fades the scrim with the pull, and gives it back on release', () => {
        drag(find(window.document, '#head'), 10, HEIGHT / 2)
        assert.equal(drawer().style.getPropertyValue('--meili-scrim-shown'), '0.5')

        pointer('pointercancel', sheet(), 300)
        assert.equal(drawer().style.getPropertyValue('--meili-scrim-shown'), '')
    })

    /** Caught on its way back, the sheet goes on from where it is instead of jumping to the finger. */
    it('picks the sheet up where its transition has it', () => {
        const computed = window.getComputedStyle.bind(window)
        window.getComputedStyle = ((node: Element) => (node === sheet() ? { transform: 'matrix(1, 0, 0, 1, 0, 40)' } : computed(node))) as typeof window.getComputedStyle

        drag(find(window.document, '#head'), 10, 20)

        assert.equal(sheet().style.transform, 'translateY(60px)')
    })

    /** The drawer closes or outgrows the sheet mid-drag: nothing the finger wrote stays behind. */
    it('takes back all it wrote inline when cancelled mid-drag', () => {
        drag(find(window.document, '#head'), 10, HEIGHT / 2)

        gesture.cancel()

        assert.equal(sheet().style.transform, '')
        assert.equal(drawer().style.getPropertyValue('--meili-scrim-shown'), '')
        assert.equal(drawer().hasAttribute('data-dragging'), false)

        pointer('pointermove', sheet(), 300)
        assert.equal(sheet().style.transform, '')
    })

    it('resists a pull past its top', () => {
        drag(find(window.document, '#head'), 10, -16)

        assert.equal(sheet().style.transform, 'translateY(-4px)')
    })

    it('goes back when a slow pull stops short of a quarter of the sheet', () => {
        drag(find(window.document, '#head'), 10)
        later(250)
        pointer('pointermove', sheet(), 120)
        lift(20)

        assert.equal(dismissed, 0)
        assert.equal(sheet().style.transform, '')
        assert.equal(drawer().hasAttribute('data-dragging'), false)
    })

    it('dismisses past a quarter of the sheet, however slow', () => {
        drag(find(window.document, '#head'), 10)
        later(250)
        pointer('pointermove', sheet(), 100 + HEIGHT / 4 + 1)
        lift(HEIGHT / 4 + 1)

        assert.equal(dismissed, 1)
        assert.equal(sheet().style.transform, '')
    })

    it('dismisses on a short flick', () => {
        drag(find(window.document, '#head'), 10, 40)
        lift(40)

        assert.equal(dismissed, 1)
    })

    it('scrolls a body away from its top instead of pulling the sheet', () => {
        find(window.document, '#body').scrollTop = 50

        drag(find(window.document, '#text'), 10, 200)
        lift(200)

        assert.equal(sheet().style.transform, '')
        assert.equal(dismissed, 0)
    })

    it('pulls from a body at its top', () => {
        drag(find(window.document, '#text'), 10, 200)
        lift(200)

        assert.equal(dismissed, 1)
    })

    it('leaves a push up over a list that scrolls to the scroll', () => {
        const body = find(window.document, '#body')
        body.style.overflowY = 'auto'
        Object.defineProperty(body, 'scrollHeight', { value: 900 })
        Object.defineProperty(body, 'clientHeight', { value: 300 })

        drag(find(window.document, '#text'), -10, -200)

        assert.equal(sheet().style.transform, '')
    })

    /** From the handle or the head, a pull up stretches the sheet, resisting: nothing there scrolls. */
    it('stretches the sheet on a push up from where nothing scrolls', () => {
        drag(find(window.document, '#head'), -10, -100)

        assert.equal(sheet().style.transform, 'translateY(-10px)')
    })

    /** A slow pull that ends still is not a flick, however long it lasted. */
    it('takes the speed of the end of the gesture, not of all of it', () => {
        drag(find(window.document, '#head'), 10, 80)
        later(150)
        pointer('pointermove', sheet(), 180)
        lift(80)

        assert.equal(dismissed, 0)
    })

    it('leaves a tap to the click', () => {
        drag(find(window.document, '#head'), 2)
        lift(2)

        assert.equal(drawer().hasAttribute('data-dragging'), false)
        assert.equal(dismissed, 0)
    })

    it('leaves a field its own press', () => {
        drag(find(window.document, '#field'), 10, 200)

        assert.equal(sheet().style.transform, '')
    })

    /** R-176: Chrome captures a touch on whatever it lands on, so a capture says nothing about who owns the press. */
    it('follows a finger the browser captured on the head, as it does on any touch', () => {
        const title = find(window.document, '#title')
        title.addEventListener('pointerdown', () => title.setPointerCapture(1))

        drag(title, 10, 60)

        assert.equal(sheet().style.transform, 'translateY(60px)')
        assert.ok(sheet().hasPointerCapture(1))
    })

    it('follows a finger from the handle', () => {
        pointer('pointerdown', find(window.document, '#handle'), 100, { pointerType: 'touch' })
        pointer('pointermove', find(window.document, '#handle'), 140, { pointerType: 'touch' })

        assert.equal(sheet().style.transform, 'translateY(40px)')
    })

    it('leaves the price track, its handles and the close button their own press, whatever the pointer', () => {
        for (const id of ['#track', '#handle-min', '#close']) {
            for (const pointerType of ['touch', 'mouse']) {
                pointer('pointerdown', find(window.document, id), 100, { pointerType })
                pointer('pointermove', find(window.document, id), 300, { pointerType })
                pointer('pointerup', find(window.document, id), 300, { pointerType })

                assert.equal(sheet().style.transform, '', `${id} (${pointerType})`)
                assert.equal(dismissed, 0, `${id} (${pointerType})`)
            }
        }
    })

    it('follows one finger, and ignores a second', () => {
        drag(find(window.document, '#head'), 10)
        pointer('pointerdown', find(window.document, '#head'), 100, { pointerId: 2, isPrimary: false })
        pointer('pointermove', sheet(), 300, { pointerId: 2, isPrimary: false })

        assert.equal(sheet().style.transform, 'translateY(10px)')
    })

    it('ignores a press on the scrim, and any press while closed', () => {
        drag(drawer(), 10, 200)
        assert.equal(drawer().hasAttribute('data-dragging'), false)

        open = false
        drag(find(window.document, '#head'), 10, 200)
        assert.equal(drawer().hasAttribute('data-dragging'), false)
    })

    it('goes back when the browser takes the pointer', () => {
        drag(find(window.document, '#head'), 10, 200)

        pointer('pointercancel', sheet(), 300)

        assert.equal(sheet().style.transform, '')
        assert.equal(drawer().hasAttribute('data-dragging'), false)
        assert.equal(dismissed, 0)
    })

    it('does not move under reduced motion, and still dismisses', () => {
        reduced.matches = true

        drag(find(window.document, '#head'), 10, 200)
        assert.equal(sheet().style.transform, '')

        lift(200)
        assert.equal(dismissed, 1)
    })

    it('keeps the body from scrolling under a pull down, and not under a push up', () => {
        const touch = (clientY: number) => {
            const event = new window.TouchEvent('touchmove', { bubbles: true, cancelable: true, touches: [{ clientY } as Touch] })
            find(window.document, '#text').dispatchEvent(event)

            return event.defaultPrevented
        }

        pointer('pointerdown', find(window.document, '#text'), 100)

        assert.equal(touch(90), false)
        assert.equal(touch(110), true)
    })
})
