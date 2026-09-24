import type { Contract } from '../shared/contract.ts'
import type { Card } from '../shared/description.ts'

/** Writes one projected card into a node the theme rendered. */
export class CardView {
    #contract: Contract

    constructor(contract: Contract) {
        this.#contract = contract
    }

    show(node: Element, card: Card) {
        this.#link(this.#contract.one('url', node), card)
        this.#image(this.#contract.one('image', node), card)
        this.#text(this.#contract.one('title', node), card.title)
        this.#markup(this.#contract.one('price', node), card.price)

        return node
    }

    #link(node: Element | null, card: Card) {
        if (node instanceof HTMLAnchorElement) {
            node.href = this.#textOf(card.url)
        }
    }

    #image(node: Element | null, card: Card) {
        if (!(node instanceof HTMLImageElement)) {
            return
        }

        const source = card.image_url

        node.hidden = typeof source !== 'string' || source === ''
        node.alt = this.#textOf(card.image_alt) || this.#textOf(card.title)

        if (!node.hidden) {
            node.src = String(source)
            this.#size(node, 'width', card.image_width)
            this.#size(node, 'height', card.image_height)
        }
    }

    /**
     * A dimension the index cannot have meant is dropped rather than guessed:
     * lying to the browser causes the very shift it is meant to prevent.
     */
    #size(node: HTMLImageElement, attribute: 'width' | 'height', value: unknown) {
        if (Number.isInteger(value) && Number(value) > 0) {
            node.setAttribute(attribute, String(value))
        } else {
            node.removeAttribute(attribute)
        }
    }

    #text(node: Element | null, value: unknown) {
        if (node) {
            node.textContent = this.#textOf(value)
        }
    }

    #textOf(value: unknown) {
        return typeof value === 'string' || typeof value === 'number' ? String(value) : ''
    }

    /**
     * WooCommerce formats a price with markup, and the server escaped it once
     * already: writing it as text would show the tags.
     */
    #markup(node: Element | null, value: unknown) {
        if (!(node instanceof HTMLElement)) {
            return
        }

        const markup = typeof value === 'string' ? value : ''

        node.hidden = markup === ''
        node.innerHTML = markup
    }
}
