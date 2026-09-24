import { isDrawn } from './drawn.ts'

import type { Contract } from '../shared/contract.ts'
import type { Drawn } from './drawn.ts'

const ACTIVE_HANDLE = 'data-active'

const MAIN_BUTTON = 0

const NO_BUTTON = 0

/** `pointerup` is not the only ending: a capture lost to a context menu or to another window is one too. */
const GESTURE_ENDINGS = ['pointerup', 'pointercancel', 'lostpointercapture'] as const

type PointerGesture = 'pointermove' | typeof GESTURE_ENDINGS[number]

export class SliderDrag {
    #track: Drawn | null
    #grabbed: Drawn | null = null
    #captured: number | null = null

    constructor(contract: Contract) {
        const track = contract.one('price-track')

        this.#track = isDrawn(track) ? track : null
    }

    get grabbed() {
        return this.#grabbed
    }

    onMove(listener: (event: PointerEvent) => void) {
        this.#on('pointermove', (event) => {
            if (event.buttons !== NO_BUTTON) {
                listener(event)
            }
        })
    }

    onRelease(listener: () => void) {
        GESTURE_ENDINGS.forEach((ending) => this.#on(ending, listener))

        // A move with nothing pressed ends a gesture the browser took elsewhere: a menu, another window.
        this.#on('pointermove', (event) => {
            if (event.buttons === NO_BUTTON) {
                listener()
            }
        })
    }

    grab(handle: Drawn, event: PointerEvent) {
        if (event.button !== MAIN_BUTTON) {
            return
        }

        this.#mark(handle)
        this.#captured = event.pointerId
        this.#track?.setPointerCapture(event.pointerId)
    }

    /** `:active` stays on the handle that was pressed, while a crossing hands the drag to the other. */
    handOver(handle: Drawn | null) {
        this.#mark(handle)
    }

    /** A capture the browser has not taken back holds every later press: the handle would never see one again. */
    release() {
        if (this.#captured !== null && this.#track?.hasPointerCapture(this.#captured)) {
            this.#track.releasePointerCapture(this.#captured)
        }

        this.#captured = null
        this.#mark(null)
    }

    ratioAt(event: PointerEvent) {
        if (this.#track === null) {
            return null
        }

        const box = this.#track.getBoundingClientRect()

        return Math.min(Math.max((event.clientX - box.left) / box.width, 0), 1)
    }

    #on(gesture: PointerGesture, listener: (event: PointerEvent) => void) {
        this.#track?.addEventListener(gesture, listener)
    }

    #mark(handle: Drawn | null) {
        this.#grabbed?.removeAttribute(ACTIVE_HANDLE)
        this.#grabbed = handle
        handle?.setAttribute(ACTIVE_HANDLE, '')
    }
}
