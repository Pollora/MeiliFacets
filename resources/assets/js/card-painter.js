/**
 * @import { Contract } from './contract.js'
 */

/**
 * Writes one projected card into a node the theme rendered. The document is a
 * store of its own: a field can be missing, so an element the card says nothing
 * about is hidden rather than left showing what the previous card held.
 */
export class CardPainter {
    /** @type {Contract} */
    #contract

    /**
     * @param {Contract} contract
     */
    constructor(contract) {
        this.#contract = contract
    }

    /**
     * @param {Element} node
     * @param {Record<string, unknown>} card
     */
    paint(node, card) {
        this.#link(this.#contract.one('url', node), card)
        this.#image(this.#contract.one('image', node), card)
        this.#text(this.#contract.one('title', node), card.title)
        this.#markup(this.#contract.one('price', node), card.price)

        return node
    }

    /**
     * @param {Element | null} node
     * @param {Record<string, unknown>} card
     */
    #link(node, card) {
        if (node instanceof HTMLAnchorElement) {
            node.href = String(card.url ?? '')
        }
    }

    /**
     * @param {Element | null} node
     * @param {Record<string, unknown>} card
     */
    #image(node, card) {
        if (!(node instanceof HTMLImageElement)) {
            return
        }

        const source = card.image_url

        node.hidden = typeof source !== 'string' || source === ''
        node.alt = String(card.image_alt ?? card.title ?? '')

        if (!node.hidden) {
            node.src = String(source)
            this.#size(node, 'width', card.image_width)
            this.#size(node, 'height', card.image_height)
        }
    }

    /**
     * A dimension the index cannot have meant is dropped rather than guessed:
     * lying to the browser causes the very shift it is meant to prevent.
     *
     * @param {HTMLImageElement} node
     * @param {'width' | 'height'} attribute
     * @param {unknown} value
     */
    #size(node, attribute, value) {
        Number.isInteger(value) && Number(value) > 0
            ? node.setAttribute(attribute, String(value))
            : node.removeAttribute(attribute)
    }

    /**
     * @param {Element | null} node
     * @param {unknown} value
     */
    #text(node, value) {
        if (node) {
            node.textContent = String(value ?? '')
        }
    }

    /**
     * WooCommerce formats a price with markup, and the server escaped it once
     * already: writing it as text would show the tags.
     *
     * @param {Element | null} node
     * @param {unknown} value
     */
    #markup(node, value) {
        if (!(node instanceof HTMLElement)) {
            return
        }

        const markup = typeof value === 'string' ? value : ''

        node.hidden = markup === ''
        node.innerHTML = markup
    }
}
