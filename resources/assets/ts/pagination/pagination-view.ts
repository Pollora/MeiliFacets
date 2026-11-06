import type { Contract } from '../shared/contract.ts'
import type { PageWindow } from './page-window.ts'

const CURRENT = 'aria-current'

const HREF = 'href'

export type LinkTo = (page: number) => string

/** The links the theme rendered, revealed and pointed elsewhere: none is created, none removed. */
export class PaginationView {
    #contract: Contract

    #nodes: { nav: Element | null, previous: Element | null, next: Element | null, pages: Element[] } | null = null

    constructor(contract: Contract) {
        this.#contract = contract
    }

    show(pageWindow: PageWindow, linkTo: LinkTo) {
        const { nav, previous, next, pages: links } = this.#markup()

        if (!(nav instanceof HTMLElement)) {
            return
        }

        const focused = this.#focusedIn(nav)

        nav.hidden = !pageWindow.hasPages
        this.#showStep(previous, pageWindow.hasPrevious, linkTo(pageWindow.previous))
        this.#showStep(next, pageWindow.hasNext, linkTo(pageWindow.next))
        this.#showNumbers(links, pageWindow, linkTo)
        this.#keepTheFocus(links, focused)
    }

    #markup() {
        return this.#nodes ??= {
            nav: this.#contract.one('pagination'),
            previous: this.#contract.one('previous'),
            next: this.#contract.one('next'),
            pages: this.#contract.all('page'),
        }
    }

    #showStep(link: Element | null, reachable: boolean, href: string) {
        if (link instanceof HTMLAnchorElement) {
            link.setAttribute(HREF, href)
            link.hidden = !reachable
        }
    }

    #showNumbers(links: Element[], pageWindow: PageWindow, linkTo: LinkTo) {
        const slots = pageWindow.slots(links.length)

        links.forEach((link, rank) => this.#showNumber(link, slots[rank] ?? null, { current: pageWindow.current, linkTo }))
    }

    #showNumber(link: Element, page: number | null, { current, linkTo }: { current: number, linkTo: LinkTo }) {
        if (!(link instanceof HTMLAnchorElement)) {
            return
        }

        link.hidden = page === null
        link.textContent = page === null ? '' : String(page)

        if (page === null) {
            link.removeAttribute(HREF)
        } else {
            link.setAttribute(HREF, linkTo(page))
        }

        if (page === current) {
            link.setAttribute(CURRENT, 'page')
        } else {
            link.removeAttribute(CURRENT)
        }
    }

    #focusedIn(nav: HTMLElement): HTMLElement | null {
        const focused = nav.ownerDocument.activeElement

        return focused instanceof HTMLElement && nav.contains(focused) ? focused : null
    }

    /** Hiding the link that held the focus drops it on the document body. */
    #keepTheFocus(links: Element[], focused: HTMLElement | null) {
        if (focused === null || !focused.hidden) {
            return
        }

        const current = links.find((link) => link.hasAttribute(CURRENT))

        if (current instanceof HTMLElement) {
            // Without this the browser scrolls the focused link back into view.
            current.focus({ preventScroll: true })
        }
    }
}
