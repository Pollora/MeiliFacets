import { CardPainter } from './card-painter.js'

/**
 * @import { Contract } from './contract.js'
 */

/** The grid the theme rendered, and the message shown in its place when nothing matched. */
export class ResultsView {
    /** @type {Contract} */
    #contract

    /** @type {CardPainter} */
    #painter

    /**
     * @param {Contract} contract
     */
    constructor(contract) {
        this.#contract = contract
        this.#painter = new CardPainter(contract)
    }

    /**
     * @param {Record<string, unknown>[]} cards
     */
    show(cards) {
        const list = this.#contract.one('results')
        const template = this.#contract.one('card-template')
        const empty = this.#contract.one('empty')

        if (!(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
            return
        }

        list.replaceChildren(...cards.map((card) => this.#card(template, card)))
        list.hidden = cards.length === 0

        if (empty instanceof HTMLElement) {
            empty.hidden = cards.length > 0
        }
    }

    /**
     * @param {HTMLTemplateElement} template
     * @param {Record<string, unknown>} card
     */
    #card(template, card) {
        const node = /** @type {Element} */ (template.content.firstElementChild?.cloneNode(true))

        return this.#painter.paint(node, card)
    }
}
