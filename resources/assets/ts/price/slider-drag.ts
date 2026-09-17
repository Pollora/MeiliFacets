import { isDrawn } from './drawn.ts'

import type { Contract } from '../shared/contract.ts'
import type { Drawn } from './drawn.ts'

const ACTIVE_HANDLE = 'data-active'

export class SliderDrag {
    #track: Drawn | null
    #grabbed: Drawn | null = null

    constructor(contract: Contract) {
        const track = contract.one('price-track')

        this.#track = isDrawn(track) ? track : null
    }

    get grabbed() {
        return this.#grabbed
    }

    onMove(listener: (event: PointerEvent) => void) {
        this.#track?.addEventListener('pointermove', listener)
    }

    onRelease(listener: () => void) {
        this.#track?.addEventListener('pointerup', listener)
        this.#track?.addEventListener('pointercancel', listener)
    }

    grab(handle: Drawn, event: PointerEvent) {
        this.#mark(handle)
        this.#track?.setPointerCapture(event.pointerId)
    }

    /** `:active` stays on the handle that was pressed, while a crossing hands the drag to the other. */
    handOver(handle: Drawn | null) {
        this.#mark(handle)
    }

    release() {
        this.#mark(null)
    }

    ratioAt(event: PointerEvent) {
        if (this.#track === null) {
            return null
        }

        const box = this.#track.getBoundingClientRect()

        return Math.min(Math.max((event.clientX - box.left) / box.width, 0), 1)
    }

    #mark(handle: Drawn | null) {
        this.#grabbed?.removeAttribute(ACTIVE_HANDLE)
        this.#grabbed = handle
        handle?.setAttribute(ACTIVE_HANDLE, '')
    }
}
