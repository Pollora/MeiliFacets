import { PanelTransitions } from '../shared/panel-transitions.ts'
import { SUBPIXEL } from '../shared/subpixel.ts'

import type { CssTiming } from '../shared/css-timing.ts'
import type { EntranceTiming } from '../shared/entrance.ts'

const RESIZE_ID = 'meilifacets-panel-resize'

export interface HeightBefore {
    seen: number
    panelEnteringOrLeaving: boolean
    changesAtOnce: boolean
}

export const HEIGHT_UNREAD: HeightBefore = { seen: 0, panelEnteringOrLeaving: false, changesAtOnce: true }

/** The panel's height between two answers, eased from the height the eye sees to the one its content asks for. */
export class PanelHeight {
    #panel: HTMLElement
    #timing: CssTiming
    #transitions: PanelTransitions

    constructor(panel: HTMLElement, timing: CssTiming) {
        this.#panel = panel
        this.#timing = timing
        this.#transitions = new PanelTransitions(panel)
    }

    before(): HeightBefore {
        const panelEnteringOrLeaving = this.#isEnteringOrLeaving()

        return {
            seen: this.#seen(),
            panelEnteringOrLeaving,
            changesAtOnce: panelEnteringOrLeaving || this.#timing.prefersReducedMotion(),
        }
    }

    after(before: HeightBefore) {
        this.#runningResizes().forEach((resize) => resize.cancel())

        return new PanelResize(this.#panel, before, this.#seen())
    }

    #isEnteringOrLeaving() {
        return this.#transitions.enteringOrLeaving().length > 0
    }

    #runningResizes() {
        return this.#panel.getAnimations().filter((animation) => animation.id === RESIZE_ID)
    }

    #seen() {
        return this.#panel.getBoundingClientRect().height
    }
}

export class PanelResize {
    #panel: HTMLElement
    #from: number
    #to: number
    #changesAtOnce: boolean

    constructor(panel: HTMLElement, { seen, changesAtOnce }: HeightBefore, to: number) {
        this.#panel = panel
        this.#from = seen
        this.#to = to
        this.#changesAtOnce = changesAtOnce
    }

    overhang() {
        return this.#eases() ? Math.max(0, this.#from - this.#to) : 0
    }

    /** The content is clipped while the edge moves: a scrollbar would flash while the panel grows to fit it. */
    play(timing: EntranceTiming) {
        const clipped = { overflowY: 'hidden' }

        if (this.#eases()) {
            this.#panel.animate([{ ...clipped, height: `${this.#from}px` }, { ...clipped, height: `${this.#to}px` }], { ...timing, id: RESIZE_ID })
        }
    }

    #eases() {
        return this.#isShownAndAnimated() && this.#heightChanged()
    }

    #isShownAndAnimated() {
        return !this.#changesAtOnce && !this.#panel.hidden
    }

    #heightChanged() {
        return Math.abs(this.#from - this.#to) >= SUBPIXEL
    }
}
