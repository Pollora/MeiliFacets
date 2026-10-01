import { CardFieldValue } from './card-field-value.ts'
import { CardBinding } from './card-binding.ts'

import type { Contract } from '../shared/contract.ts'
import type { Card } from '../shared/description.ts'
import type { SearchHit } from '../shared/search-client.ts'

const ID_FIELD = 'id'

/** Writes one projected card into a node the theme rendered. */
export class CardView {
    #contract: Contract

    constructor(contract: Contract) {
        this.#contract = contract
    }

    static fieldsOf(hit: SearchHit): Card {
        return hit.ID === undefined ? { ...hit.card } : { ...hit.card, [ID_FIELD]: hit.ID }
    }

    /** A copy of the template's first node, filled; nothing when the template holds no element. */
    stamp(template: HTMLTemplateElement, card: Card) {
        const node = template.content.firstElementChild?.cloneNode(true)

        return node instanceof Element ? [this.show(node, card)] : []
    }

    show(node: Element, card: Card) {
        new CardBinding(card).apply(node)
        this.#markup(this.#contract.one('price', node), card.price)

        return node
    }

    /**
     * WooCommerce formats a price with markup, and the server escaped it once
     * already: writing it as text would show the tags.
     */
    #markup(node: Element | null, value: unknown) {
        const markup = new CardFieldValue(value).text

        if (markup === '') {
            node?.remove()
        } else if (node !== null) {
            node.innerHTML = markup
        }
    }
}
