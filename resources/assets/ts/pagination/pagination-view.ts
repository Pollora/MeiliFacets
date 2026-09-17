import type { Contract } from '../shared/contract.ts'
import type { PageWindow } from './page-window.ts'

const CURRENT = 'aria-current'

/** The numbers the theme rendered, revealed and relabelled: none is created, none removed. */
export class PaginationView {
    #contract: Contract

    /** The nav and its buttons are never recreated: the client reveals them. */
    #nodes: { nav: Element | null, previous: Element | null, next: Element | null, pages: Element[] } | null = null

    constructor(contract: Contract) {
        this.#contract = contract
    }

    show(pageWindow: PageWindow) {
        const { nav, previous, next, pages: buttons } = this.#markup()

        if (!(nav instanceof HTMLElement)) {
            return
        }

        const pressed = this.#focusedIn(nav)

        nav.hidden = !pageWindow.hasPages
        this.#showStep(previous, pageWindow.hasPrevious, pageWindow.previous)
        this.#showStep(next, pageWindow.hasNext, pageWindow.next)
        this.#showNumbers(buttons, pageWindow)
        this.#keepTheFocus(buttons, pressed)
    }

    #markup() {
        return this.#nodes ??= {
            nav: this.#contract.one('pagination'),
            previous: this.#contract.one('previous'),
            next: this.#contract.one('next'),
            pages: this.#contract.all('page'),
        }
    }

    #showStep(button: Element | null, reachable: boolean, page: number) {
        if (button instanceof HTMLButtonElement) {
            button.value = String(page)
            button.hidden = !reachable
        }
    }

    #showNumbers(buttons: Element[], pageWindow: PageWindow) {
        const slots = pageWindow.slots(buttons.length)

        buttons.forEach((button, rank) => this.#showNumber(button, slots[rank] ?? null, pageWindow.current))
    }

    #showNumber(button: Element, page: number | null, current: number) {
        if (!(button instanceof HTMLButtonElement)) {
            return
        }

        button.hidden = page === null
        button.value = page === null ? '' : String(page)
        button.textContent = button.value

        if (page === current) {
            button.setAttribute(CURRENT, 'page')
        } else {
            button.removeAttribute(CURRENT)
        }
    }

    #focusedIn(nav: HTMLElement): HTMLElement | null {
        const focused = nav.ownerDocument.activeElement

        return focused instanceof HTMLElement && nav.contains(focused) ? focused : null
    }

    /** Hiding the button that was pressed drops the focus on the document body. */
    #keepTheFocus(buttons: Element[], pressed: HTMLElement | null) {
        if (pressed === null || !pressed.hidden) {
            return
        }

        const current = buttons.find((button) => button.hasAttribute(CURRENT))

        if (current instanceof HTMLElement) {
            // Without this the browser scrolls the focused button back into view,
            // undoing the move to the top of the listing.
            current.focus({ preventScroll: true })
        }
    }
}
