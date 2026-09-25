import { Contract } from '../shared/contract.ts'

const DRAGGING = 'data-dragging'
const SHEET = Contract.selector('drawer-sheet')
const SCRIM = '--meili-scrim-shown'
/** What moves under its own press: a field, the price's track and handles, and the ✕ the head keeps for its tap. */
const OWN_GESTURES = [
    'input',
    'select',
    'textarea',
    Contract.selector('price-track'),
    Contract.selector('price-handle'),
    `button${Contract.selector('drawer-close')}`,
].join(', ')

/** Vaul's thresholds: a quarter of the sheet, or a flick in pixels per millisecond. */
const DISMISS_SHARE = 0.25
const FLICK_SPEED = 0.11

/** Below this, a press is a tap: the drag does not start, and the click goes through. */
const SLOP = 4

/** The window a flick's speed is measured over. */
const RECENT_MS = 100

interface Dismissible {
    isOpen(): boolean
    dismiss(): void
}

interface Sample {
    offset: number
    at: number
}

interface Drag {
    pointerId: number
    sheet: HTMLElement
    startY: number
    from: number
    height: number
    offset: number
    moving: boolean
    scrolls: boolean
    recent: Sample[]
}

export class DrawerGesture {
    #drawer: HTMLElement
    #dismissible: Dismissible
    #reduced: MediaQueryList
    #drag: Drag | null = null

    constructor(drawer: HTMLElement, dismissible: Dismissible, reduced: MediaQueryList) {
        this.#drawer = drawer
        this.#dismissible = dismissible
        this.#reduced = reduced
    }

    start() {
        this.#drawer.addEventListener('pointerdown', (event) => this.#pressed(event))
        this.#drawer.addEventListener('pointermove', (event) => this.#moved(event))
        this.#drawer.addEventListener('pointerup', (event) => this.#released(event))
        this.#drawer.addEventListener('pointercancel', (event) => this.#dropped(event))
        this.#drawer.addEventListener('touchmove', (event) => this.#touched(event), { passive: false })

        return this
    }

    /** A drag the drawer closed or outgrew under the finger: its inline position goes with it. */
    cancel() {
        const drag = this.#drag

        this.#drag = null

        if (drag?.moving) {
            this.#settle(drag)
        }
    }

    #pressed(event: PointerEvent) {
        const sheet = this.#sheetUnder(event.target)

        if (this.#drag !== null || !event.isPrimary || !this.#dismissible.isOpen() || sheet === null) {
            return
        }

        if (!this.#heldElsewhere(event)) {
            this.#drag = {
                pointerId: event.pointerId,
                sheet,
                startY: event.clientY,
                from: 0,
                height: 0,
                offset: 0,
                moving: false,
                scrolls: this.#scrollsUnder(event.target),
                recent: [{ offset: 0, at: event.timeStamp }],
            }
        }
    }

    #moved(event: PointerEvent) {
        const drag = this.#dragOf(event)

        if (drag === null) {
            return
        }

        drag.offset = event.clientY - drag.startY
        drag.recent = [...drag.recent.filter((sample) => event.timeStamp - sample.at <= RECENT_MS), { offset: drag.offset, at: event.timeStamp }]

        if (!drag.moving && !this.#begins(drag, event)) {
            return
        }

        this.#follow(drag)
    }

    #begins(drag: Drag, event: PointerEvent) {
        if (Math.abs(drag.offset) < SLOP) {
            return false
        }

        if (drag.offset < 0 && drag.scrolls) {
            this.#drag = null

            return false
        }

        drag.moving = true
        drag.from = this.#shownAt(drag.sheet)
        drag.height = drag.sheet.offsetHeight
        drag.sheet.setPointerCapture(event.pointerId)
        this.#drawer.setAttribute(DRAGGING, '')

        return true
    }

    #follow(drag: Drag) {
        if (this.#reduced.matches) {
            return
        }

        const at = this.#resisted(drag.from + drag.offset)

        drag.sheet.style.transform = `translateY(${at}px)`
        this.#drawer.style.setProperty(SCRIM, String(1 - Math.min(Math.max(at, 0) / drag.height, 1)))
    }

    /** Where the stylesheet's transition has the sheet right now: a matrix, or `none` at rest. */
    #shownAt(sheet: HTMLElement) {
        const matrix = /matrix\(([^)]*)\)/.exec(this.#drawer.ownerDocument.defaultView?.getComputedStyle(sheet).transform ?? '')

        return Number(matrix?.[1]?.split(',')[5] ?? 0) || 0
    }

    #resisted(offset: number) {
        return offset >= 0 ? offset : -Math.sqrt(-offset)
    }

    #released(event: PointerEvent) {
        const drag = this.#dragOf(event)

        this.#drag = null

        if (drag === null || !drag.moving) {
            return
        }

        this.#settle(drag)

        if (this.#dismisses(drag, event.timeStamp)) {
            this.#dismissible.dismiss()
        }
    }

    #dismisses(drag: Drag, now: number) {
        const since = drag.recent.find((sample) => now - sample.at <= RECENT_MS) ?? { offset: drag.offset, at: now }
        const speed = (drag.offset - since.offset) / Math.max(now - since.at, 1)

        return drag.from + drag.offset > drag.height * DISMISS_SHARE || speed > FLICK_SPEED
    }

    #dropped(event: PointerEvent) {
        if (this.#dragOf(event) !== null) {
            this.cancel()
        }
    }

    /** The inline position goes, and the stylesheet's transition carries the sheet from there. */
    #settle(drag: Drag) {
        this.#drawer.removeAttribute(DRAGGING)
        this.#drawer.style.removeProperty(SCRIM)
        drag.sheet.style.removeProperty('transform')
    }

    /** A pull down at the top must not scroll the body first: the browser would cancel the pointer. */
    #touched(event: TouchEvent) {
        const touch = event.touches[0]
        const drag = this.#drag

        if (drag !== null && touch !== undefined && (drag.moving || touch.clientY > drag.startY)) {
            event.preventDefault()
        }
    }

    #sheetUnder(target: EventTarget | null) {
        const sheet = target instanceof Element ? target.closest(SHEET) : null

        return sheet instanceof HTMLElement && this.#drawer.contains(sheet) ? sheet : null
    }

    /** Not `hasPointerCapture()`: a touch captures its pointer on whatever it lands on, the drawer's own head included. */
    #heldElsewhere(event: PointerEvent) {
        for (let node = event.target instanceof Element ? event.target : null; node !== null && node !== this.#drawer; node = node.parentElement) {
            if (node.matches(OWN_GESTURES) || node.scrollTop > 0) {
                return true
            }
        }

        return false
    }

    #scrollsUnder(target: EventTarget | null) {
        const view = this.#drawer.ownerDocument.defaultView

        for (let node = target instanceof Element ? target : null; node !== null && node !== this.#drawer; node = node.parentElement) {
            const overflow = view?.getComputedStyle(node).overflowY

            if ((overflow === 'auto' || overflow === 'scroll') && node.scrollHeight > node.clientHeight) {
                return true
            }
        }

        return false
    }

    #dragOf(event: PointerEvent) {
        return this.#drag?.pointerId === event.pointerId ? this.#drag : null
    }
}
