import { ACTIVE_OPTION, LEAVING } from '../shared/attributes.ts'

import type { EntranceTiming } from '../shared/entrance.ts'

/** Where a node stood before an answer was written: its box, its opacity mid-fade, and the list it sat in. */
export interface Place {
    rect: DOMRect
    opacity: number
    parent: Element | null
}

/** The corner a positioned child is placed from, in the viewport: padding edge, less what is scrolled. */
export interface Origin {
    left: number
    top: number
}

/** Read once the answer is written: where each frame places its ghosts, and where the panel stops showing them. */
export interface Frames {
    origins: Map<Element, Origin>
    /** A ghost reaching past it would stretch what the panel scrolls, and a scrollbar would flash for the fade. */
    bottom: number
}

/**
 * What leaves with one answer, faded out where it stood but out of the flow at once: nothing waits for it,
 * nothing around it moves because of it. A card is its own ghost; a section or a message, which the next
 * answer may show again, leaves a copy behind.
 */
export class Departure {
    #frames: Frames
    #timing: EntranceTiming

    constructor(frames: Frames, timing: EntranceTiming) {
        this.#frames = frames
        this.#timing = timing
    }

    /** A read: called once the answer is written, before anything is written again. */
    static read(panel: Element, lists: Element[]): Frames {
        const frames = [...new Set([panel, ...lists])].filter((frame) => frame.isConnected)
        const box = panel.getBoundingClientRect()

        return {
            origins: new Map(frames.map((frame) => [frame, frame === panel ? Departure.#originIn(frame, box) : Departure.#originOf(frame)])),
            bottom: box.top + panel.clientTop + panel.clientHeight,
        }
    }

    static #originOf(frame: Element) {
        return Departure.#originIn(frame, frame.getBoundingClientRect())
    }

    static #originIn(frame: Element, box: DOMRect): Origin {
        return { left: box.left + frame.clientLeft - frame.scrollLeft, top: box.top + frame.clientTop - frame.scrollTop }
    }

    /** A card still in the document stayed in a section that left: the section's copy carries it out. */
    row(row: Element, place: Place) {
        const origin = place.parent === null ? undefined : this.#frames.origins.get(place.parent)

        if (row.isConnected || place.parent === null || origin === undefined || !this.#inSight(place)) {
            return
        }

        row.removeAttribute(ACTIVE_OPTION)
        place.parent.append(row)
        this.#fade(row, place, origin)
    }

    copy(node: Element, place: Place, container: Element) {
        const origin = this.#frames.origins.get(container)
        const copy = node.cloneNode(true)

        if (origin === undefined || !(copy instanceof HTMLElement) || !this.#inSight(place)) {
            return
        }

        copy.hidden = false
        copy.removeAttribute('id')
        copy.querySelectorAll('[id]').forEach((named) => named.removeAttribute('id'))
        node.after(copy)
        this.#fade(copy, place, origin)
    }

    #inSight({ rect }: Place) {
        return rect.top < this.#frames.bottom
    }

    #fade(node: Element, { rect, opacity }: Place, origin: Origin) {
        if (!(node instanceof HTMLElement)) {
            return
        }

        node.setAttribute(LEAVING, '')
        node.setAttribute('aria-hidden', 'true')
        node.inert = true
        node.style.top = `${rect.top - origin.top}px`
        node.style.left = `${rect.left - origin.left}px`
        node.style.width = `${rect.width}px`
        node.style.maxHeight = `${this.#frames.bottom - rect.top}px`
        node.animate([{ opacity }, { opacity: 0 }], { ...this.#timing, fill: 'forwards' }).onfinish = () => node.remove()
    }
}
