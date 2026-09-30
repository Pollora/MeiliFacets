import type { Contract } from '../shared/contract.ts'
import type { Card } from '../shared/description.ts'

/** Writes one projected card into a node the theme rendered. */
export class CardView {
    #contract: Contract
    #altText: (card: Card) => string

    constructor(contract: Contract, altText = CardView.#altFromCard) {
        this.#contract = contract
        this.#altText = altText
    }

    static withDecorativeImages(contract: Contract) {
        return new CardView(contract, CardView.#noAltText)
    }

    /** A copy of the template's first node, filled; nothing when the template holds no element. */
    stamp(template: HTMLTemplateElement, card: Card) {
        const node = template.content.firstElementChild?.cloneNode(true)

        return node instanceof Element ? [this.show(node, card)] : []
    }

    show(node: Element, card: Card) {
        this.#link(this.#contract.one('url', node), card)
        this.#image(this.#contract.one('image', node), card)
        this.showWords(node, card)
        this.#markup(this.#contract.one('price', node), card.price)

        return node
    }

    /** The words alone: what changes on a card already drawn when another term finds it again. */
    showWords(node: Element, card: Card) {
        this.#text(this.#contract.one('title', node), card.title)
        this.#summary(this.#contract.one('summary', node), card.summary)
    }

    #link(node: Element | null, card: Card) {
        if (node instanceof HTMLAnchorElement) {
            node.href = CardView.#textOf(card.url)
        }
    }

    #image(node: Element | null, card: Card) {
        if (!(node instanceof HTMLImageElement)) {
            return
        }

        const source = card.image_url

        node.hidden = typeof source !== 'string' || source === ''
        node.alt = this.#altText(card)

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
            node.textContent = CardView.#textOf(value)
        }
    }

    #summary(node: Element | null, value: unknown) {
        if (node instanceof HTMLElement) {
            node.textContent = CardView.#textOf(value)
            node.hidden = node.textContent === ''
        }
    }

    static #altFromCard(card: Card) {
        return CardView.#textOf(card.image_alt) || CardView.#textOf(card.title)
    }

    static #noAltText() {
        return ''
    }

    static #textOf(value: unknown) {
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
