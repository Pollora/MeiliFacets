import { CONTROLS, EXPANDED, INSTANT } from '../shared/attributes.ts'
import { Contract } from '../shared/contract.ts'
import { REDUCED_MOTION } from '../shared/css-timing.ts'
import { DrawerGesture } from './drawer-gesture.ts'
import { InertPage } from './inert-page.ts'
import { DrawerSideSheet } from './drawer-side-sheet.ts'
import { SheetHeight } from './sheet-height.ts'

const MEDIA = 'data-media'
const CLOSING = 'data-closing'
const INERT = 'inert'
const MODAL = 'aria-modal'
const LABELLED_BY = 'aria-labelledby'

interface DrawerListeners {
    /** Runs once a close has played out. */
    hidden: (drawer: HTMLElement) => void
    /** Runs whenever the page behind stops being hidden by the sheet, the threshold included. */
    uncovered: () => void
}

export class Drawer {
    #contract: Contract
    #drawer: HTMLElement
    #page: InertPage
    #media: MediaQueryList
    #gesture: DrawerGesture
    #height: SheetHeight | null
    #sideSheet: DrawerSideSheet
    #opener: HTMLElement | null = null
    #listeners: DrawerListeners
    #closings = 0

    constructor(contract: Contract, drawer: HTMLElement, listeners: Partial<DrawerListeners> = {}) {
        this.#contract = contract
        this.#drawer = drawer
        this.#listeners = { hidden: () => {}, uncovered: () => {}, ...listeners }
        this.#page = new InertPage(drawer)
        this.#media = contract.window.matchMedia(drawer.getAttribute(MEDIA) ?? 'all')
        this.#gesture = new DrawerGesture(
            drawer,
            { isDraggable: () => this.#isDraggable(), dismiss: () => this.#close() },
            contract.window.matchMedia(REDUCED_MOTION),
        )
        this.#sideSheet = new DrawerSideSheet(drawer, contract)

        const sheet = contract.element('drawer-sheet', drawer)

        this.#height = sheet === null ? null : new SheetHeight(sheet)
    }

    start() {
        this.#sideSheet.start()
        this.#openers().forEach((opener) => opener.addEventListener('click', () => this.#open(opener)))
        this.#showMoreButtons().forEach((button) => button.addEventListener('click', () => this.#showMore(button)))
        this.#drawer.addEventListener('click', (event) => this.#clicked(event))
        this.#drawer.addEventListener('keydown', (event) => this.#pressed(event))
        this.#media.addEventListener('change', () => this.#resized())
        this.#gesture.start()

        return this
    }

    /** Open, or still playing its way out: the page behind is out of sight either way. */
    coversThePage() {
        return this.#isOpen() || this.#drawer.hasAttribute(CLOSING)
    }

    #showMore(button: HTMLElement) {
        if (!this.#isOpen() && !this.#media.matches) {
            this.#sideSheet.liftRow()
        }

        this.#open(button)
    }

    #open(opener: HTMLElement) {
        if (!this.#opensAsDialog() || this.#isOpen()) {
            return
        }

        const title = this.#contract.element('drawer-title', this.#drawer)

        this.#opener = opener
        this.#drawer.removeAttribute(INSTANT)
        this.#drawer.removeAttribute(CLOSING)
        this.#drawer.removeAttribute(INERT)
        this.#drawer.setAttribute('role', 'dialog')
        this.#drawer.setAttribute(MODAL, 'true')
        this.#drawer.setAttribute(LABELLED_BY, title?.id ?? '')
        this.#markExpanded()
        this.#page.seal()
        this.#height?.release()
        this.#followHeight()
        title?.focus()
    }

    #clicked(event: Event) {
        const closing = event.target === this.#drawer || this.#closes(event.target)

        if (closing && this.#isOpen()) {
            this.#close()
        }
    }

    #closes(target: EventTarget | null) {
        return target instanceof Element
            && (target.closest(Contract.selector('drawer-close')) !== null || target.closest(Contract.selector('apply')) !== null)
    }

    /** An Escape a control inside already consumed — the open sort list — closes that control only. */
    #pressed(event: KeyboardEvent) {
        if (event.key === 'Escape' && !event.defaultPrevented && this.#isOpen()) {
            event.preventDefault()
            this.#drawer.setAttribute(INSTANT, '')
            this.#close()
        }
    }

    /** The focus leaves before `inert` comes: an inert node holding the focus would drop it on `body`. */
    #close() {
        const opener = this.#opener

        this.#height?.stop()
        this.#gesture.cancel()
        this.#page.release()
        opener?.focus()
        this.#drawer.setAttribute(INERT, '')
        this.#drawer.setAttribute(CLOSING, '')
        this.#demote()
        void this.#settle(++this.#closings, opener)
    }

    /** The way out is the stylesheet's: its transitions, if any, are awaited; a reopening or a later close wins. */
    async #settle(closing: number, opener: HTMLElement | null) {
        await Promise.allSettled(this.#drawer.getAnimations({ subtree: true }).map((animation) => animation.finished))

        if (closing === this.#closings && !this.#isOpen()) {
            this.#drawer.removeAttribute(CLOSING)
            this.#drawer.removeAttribute(INERT)
            this.#height?.release()

            if (this.#sideSheet.isRowLifted()) {
                this.#sideSheet.putBackRow()

                if (this.#focusWasDropped()) {
                    opener?.focus()
                }
            }

            this.#listeners.hidden(this.#drawer)
            this.#listeners.uncovered()
        }
    }

    #resized() {
        if (this.#media.matches) {
            if (this.#isOpen()) {
                this.#putBackRowBelowThreshold()
                this.#followHeight()
            }

            return
        }

        this.#height?.release()
        this.#gesture.cancel()

        if (this.#sideSheet.isSideSheet()) {
            return
        }

        this.#drawer.removeAttribute(INERT)

        if (this.#isOpen()) {
            this.#becomeRow()
        }
    }

    #becomeRow() {
        this.#page.release()
        this.#demote()
        this.#keepFocusInSight()
        this.#listeners.uncovered()
    }

    /** The focus an inert sheet dropped lands on `body`. */
    #focusWasDropped() {
        const focused = this.#drawer.ownerDocument.activeElement

        return focused === null || focused === this.#drawer.ownerDocument.body || this.#drawer.contains(focused)
    }

    /** The row hides the sheet's title and the filters past it: a focus left there would fall on `body`. */
    #keepFocusInSight() {
        const focused = this.#drawer.ownerDocument.activeElement

        if (focused instanceof HTMLElement && this.#drawer.contains(focused) && !focused.checkVisibility()) {
            this.#showMoreButtons()[0]?.focus()
        }
    }

    /** « Show more » is hidden below the threshold: the focus it would get back on close would fall on `body`. */
    #putBackRowBelowThreshold() {
        if (this.#sideSheet.isRowLifted()) {
            this.#sideSheet.putBackRow()
            this.#opener = this.#openers()[0] ?? null
        }
    }

    #demote() {
        this.#drawer.removeAttribute('role')
        this.#drawer.removeAttribute(MODAL)
        this.#drawer.removeAttribute(LABELLED_BY)
        this.#markCollapsed()
        this.#opener = null
    }

    #markExpanded() {
        this.#controls().forEach((control) => control.setAttribute(EXPANDED, 'true'))
    }

    #markCollapsed() {
        this.#controls().forEach((control) => control.setAttribute(EXPANDED, 'false'))
    }

    #isOpen() {
        return this.#drawer.getAttribute(MODAL) === 'true'
    }

    #opensAsDialog() {
        return this.#media.matches || this.#sideSheet.isSideSheet()
    }

    #isDraggable() {
        return this.#isOpen() && this.#media.matches
    }

    #followHeight() {
        if (this.#media.matches) {
            this.#height?.follow()
        }
    }

    #controls() {
        return [...this.#openers(), ...this.#showMoreButtons()]
    }

    /** Looked up in the listing: while the drawer is the side sheet, « Show more » stands in the row before it. */
    #showMoreButtons() {
        return this.#contract.elements('drawer-show-more').filter((button) => button.getAttribute(CONTROLS) === this.#drawer.id)
    }

    #openers() {
        return this.#contract.elements('drawer-open').filter((opener) => opener.getAttribute(CONTROLS) === this.#drawer.id)
    }
}
