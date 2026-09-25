import { EXPANDED, INSTANT } from '../shared/attributes.ts'
import { Contract } from '../shared/contract.ts'
import { REDUCED_MOTION } from '../shared/css-timing.ts'
import { DrawerGesture } from './drawer-gesture.ts'
import { InertPage } from './inert-page.ts'
import { SheetHeight } from './sheet-height.ts'

const MEDIA = 'data-media'
const CLOSING = 'data-closing'
const INERT = 'inert'
const MODAL = 'aria-modal'
const LABELLED_BY = 'aria-labelledby'

interface DrawerListeners {
    /** Runs once a close has played out, out of sight: whatever it changes is never seen moving. */
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
            { isOpen: () => this.#isOpen(), dismiss: () => this.#close() },
            contract.window.matchMedia(REDUCED_MOTION),
        )
        this.#height = Drawer.#heightOf(contract.one('drawer-sheet', drawer))
    }

    static #heightOf(sheet: Element | null) {
        return sheet instanceof HTMLElement ? new SheetHeight(sheet) : null
    }

    start() {
        this.#openers().forEach((opener) => opener.addEventListener('click', () => this.#open(opener)))
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

    #open(opener: HTMLElement) {
        if (!this.#media.matches || this.#isOpen()) {
            return
        }

        this.#opener = opener
        this.#drawer.removeAttribute(INSTANT)
        this.#drawer.removeAttribute(CLOSING)
        this.#drawer.removeAttribute(INERT)
        this.#drawer.setAttribute('role', 'dialog')
        this.#drawer.setAttribute(MODAL, 'true')
        this.#drawer.setAttribute(LABELLED_BY, this.#title()?.id ?? '')
        this.#expand('true')
        this.#page.seal()
        this.#height?.release()
        this.#height?.follow()
        this.#title()?.focus()
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
        this.#height?.stop()
        this.#gesture.cancel()
        this.#page.release()
        this.#opener?.focus()
        this.#drawer.setAttribute(INERT, '')
        this.#drawer.setAttribute(CLOSING, '')
        this.#demote()
        void this.#settle(++this.#closings)
    }

    /** The way out is the stylesheet's: its transitions, if any, are awaited; a reopening or a later close wins. */
    async #settle(closing: number) {
        await Promise.allSettled(this.#drawer.getAnimations({ subtree: true }).map((animation) => animation.finished))

        if (closing === this.#closings && !this.#isOpen()) {
            this.#drawer.removeAttribute(CLOSING)
            this.#drawer.removeAttribute(INERT)
            this.#height?.release()
            this.#listeners.hidden(this.#drawer)
            this.#listeners.uncovered()
        }
    }

    #resized() {
        if (this.#media.matches) {
            return
        }

        this.#height?.release()
        this.#gesture.cancel()
        this.#drawer.removeAttribute(INERT)

        if (this.#isOpen()) {
            this.#page.release()
            this.#demote()
            this.#listeners.uncovered()
        }
    }

    #demote() {
        this.#drawer.removeAttribute('role')
        this.#drawer.removeAttribute(MODAL)
        this.#drawer.removeAttribute(LABELLED_BY)
        this.#expand('false')
        this.#opener = null
    }

    #expand(value: string) {
        this.#openers().forEach((opener) => opener.setAttribute(EXPANDED, value))
    }

    #isOpen() {
        return this.#drawer.getAttribute(MODAL) === 'true'
    }

    #title() {
        const title = this.#contract.one('drawer-title', this.#drawer)

        return title instanceof HTMLElement ? title : null
    }

    #openers() {
        return this.#contract.all('drawer-open')
            .filter((opener): opener is HTMLElement => opener instanceof HTMLElement)
            .filter((opener) => opener.getAttribute('aria-controls') === this.#drawer.id)
    }
}
