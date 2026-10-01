import { ACTIVE_OPTION, LEAVING } from '../shared/attributes.ts'

import type { AnimationTiming } from '../shared/entrance.ts'

/** Where a node stood before an answer was written: its box, its opacity mid-fade, and the list it sat in. */
export interface ExitFadeStart {
    rect: DOMRect
    opacity: number
    parent: Element | null
}

/** The corner a positioned child is placed from, in the viewport: padding edge, less what is scrolled. */
export interface ExitFadeOrigin {
    left: number
    top: number
}

/** Read once the answer is written: where each frame places its ghosts, and where the panel stops showing them. */
export interface ExitFadeLayout {
    origins: Map<Element, ExitFadeOrigin>
    /** A ghost reaching past it would stretch what the panel scrolls, and a scrollbar would flash for the fade. */
    bottom: number
}

/**
 * What leaves with one answer, faded out where it stood but out of the flow at once: nothing waits for it,
 * nothing around it moves because of it. A card is its own ghost; a section or a message, which the next
 * answer may show again, leaves a copy behind. Each drifts to `exitOffset` as it fades.
 */
export class ExitFade {
    #layout: ExitFadeLayout
    #timing: AnimationTiming
    #exitOffset: string

    constructor(layout: ExitFadeLayout, timing: AnimationTiming, exitOffset: string) {
        this.#layout = layout
        this.#timing = timing
        this.#exitOffset = exitOffset
    }

    /**
     * A read: called once the answer is written, before anything is written again. `overhang` is what the panel
     * still shows under its new edge while its height eases up to it.
     */
    static read(panel: Element, lists: Element[], overhang: number): ExitFadeLayout {
        const frames = [...new Set([panel, ...lists])].filter((frame) => frame.isConnected)
        const box = panel.getBoundingClientRect()

        return {
            origins: new Map(frames.map((frame) => [frame, frame === panel ? ExitFade.#originIn(frame, box) : ExitFade.#originOf(frame)])),
            bottom: box.top + panel.clientTop + panel.clientHeight + overhang,
        }
    }

    static #originOf(frame: Element) {
        return ExitFade.#originIn(frame, frame.getBoundingClientRect())
    }

    static #originIn(frame: Element, box: DOMRect): ExitFadeOrigin {
        return { left: box.left + frame.clientLeft - frame.scrollLeft, top: box.top + frame.clientTop - frame.scrollTop }
    }

    /** A card still in the document stayed in a section that left: the section's copy carries it out. */
    row(row: Element, start: ExitFadeStart) {
        const origin = start.parent === null ? undefined : this.#layout.origins.get(start.parent)

        if (row.isConnected || start.parent === null || origin === undefined || !this.#inSight(start)) {
            return
        }

        row.removeAttribute(ACTIVE_OPTION)
        start.parent.append(row)
        this.#fade(row, start, origin)
    }

    copy(node: Element, start: ExitFadeStart, container: Element) {
        const origin = this.#layout.origins.get(container)
        const copy = node.cloneNode(true)

        if (origin === undefined || !(copy instanceof HTMLElement) || !this.#inSight(start)) {
            return
        }

        copy.hidden = false
        copy.removeAttribute('id')
        copy.querySelectorAll('[id]').forEach((named) => named.removeAttribute('id'))
        node.after(copy)
        this.#fade(copy, start, origin)
    }

    #inSight({ rect }: ExitFadeStart) {
        return rect.top < this.#layout.bottom
    }

    #fade(node: Element, { rect, opacity }: ExitFadeStart, origin: ExitFadeOrigin) {
        if (!(node instanceof HTMLElement)) {
            return
        }

        node.setAttribute(LEAVING, '')
        node.setAttribute('aria-hidden', 'true')
        node.inert = true
        node.style.top = `${rect.top - origin.top}px`
        node.style.left = `${rect.left - origin.left}px`
        node.style.width = `${rect.width}px`
        node.style.maxHeight = `${this.#layout.bottom - rect.top}px`
        node.animate([{ opacity, transform: 'none' }, { opacity: 0, transform: this.#exitOffset }], { ...this.#timing, fill: 'forwards' }).onfinish = () => node.remove()
    }
}
