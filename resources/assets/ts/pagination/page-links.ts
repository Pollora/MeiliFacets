import { Contract } from '../shared/contract.ts'

import type { ListingUrl } from '../listing/listing-url.ts'

const LINKS = ['page', 'previous', 'next'].map((hook) => Contract.selector(hook)).join(', ')

const SAME_TAB = ['', '_self']

/** The page a plain click on a pagination link asks for; any other click is the browser's. */
export class PageLinks {
    #url: ListingUrl

    constructor(url: ListingUrl) {
        this.#url = url
    }

    pageOf(event: Event) {
        const link = event.target instanceof Element ? event.target.closest(LINKS) : null

        if (!(link instanceof HTMLAnchorElement) || !PageLinks.#isPlainClick(event) || !PageLinks.#opensHere(link)) {
            return null
        }

        return this.#url.pageAt(link.pathname)
    }

    static #isPlainClick(event: Event) {
        const click = event as MouseEvent
        const modified = click.button !== 0 || click.metaKey || click.ctrlKey || click.shiftKey || click.altKey

        return !click.defaultPrevented && !modified
    }

    /** A theme may have pointed a copied view elsewhere, or made a link a download. */
    static #opensHere(link: HTMLAnchorElement) {
        return SAME_TAB.includes(link.target)
            && !link.hasAttribute('download')
            && link.origin === link.ownerDocument.location.origin
    }
}
