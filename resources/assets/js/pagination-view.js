import { PageWindow } from './page-window.js'

/**
 * @import { Contract } from './contract.js'
 * @import { ListingDescription } from './description.js'
 * @import { ListingState } from './listing-state.js'
 */

const CURRENT = 'aria-current'

/** The numbers the theme rendered, revealed and relabelled: none is created, none removed. */
export class PaginationView {
    /** @type {Contract} */
    #contract

    /** @type {ListingDescription} */
    #description

    /**
     * The nav and its buttons are never recreated: the client reveals them.
     *
     * @type {{ nav: Element | null, previous: Element | null, next: Element | null, pages: Element[] } | null}
     */
    #nodes = null

    /**
     * @param {Contract} contract
     * @param {ListingDescription} description
     */
    constructor(contract, description) {
        this.#contract = contract
        this.#description = description
    }

    /**
     * @param {ListingState} state
     * @param {number} total
     */
    show(state, total) {
        const { nav, previous, next, pages: buttons } = this.#markup()

        if (!(nav instanceof HTMLElement)) {
            return
        }

        const pages = new PageWindow(
            state.page,
            this.#description.perPage,
            total,
            this.#description.reachableHits,
            buttons.length
        )
        const pressed = this.#focusedIn(nav)

        nav.hidden = !pages.hasPages
        this.#showStep(previous, pages.hasPrevious, pages.previous)
        this.#showStep(next, pages.hasNext, pages.next)
        this.#showNumbers(buttons, pages)
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

    /**
     * @param {Element | null} button
     * @param {boolean} reachable
     * @param {number} page
     */
    #showStep(button, reachable, page) {
        if (button instanceof HTMLButtonElement) {
            button.value = String(page)
            button.hidden = !reachable
        }
    }

    /**
     * @param {Element[]} buttons
     * @param {PageWindow} pages
     */
    #showNumbers(buttons, pages) {
        const slots = pages.slots()

        buttons.forEach((button, rank) => this.#showNumber(button, slots[rank] ?? null, pages.current))
    }

    /**
     * @param {Element} button
     * @param {number | null} page
     * @param {number} current
     */
    #showNumber(button, page, current) {
        if (!(button instanceof HTMLButtonElement)) {
            return
        }

        button.hidden = page === null
        button.value = page === null ? '' : String(page)
        button.textContent = button.value

        page === current
            ? button.setAttribute(CURRENT, 'page')
            : button.removeAttribute(CURRENT)
    }

    /**
     * @param {HTMLElement} nav
     * @returns {HTMLElement | null}
     */
    #focusedIn(nav) {
        const focused = nav.ownerDocument.activeElement

        return focused instanceof HTMLElement && nav.contains(focused) ? focused : null
    }

    /**
     * Hiding the button that was pressed drops the focus on the document body.
     *
     * @param {Element[]} buttons
     * @param {HTMLElement | null} pressed
     */
    #keepTheFocus(buttons, pressed) {
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
