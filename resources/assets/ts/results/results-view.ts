import { CardView } from './card-view.ts'

import type { PageWindow } from '../pagination/page-window.ts'
import type { Contract } from '../shared/contract.ts'
import type { Card } from '../shared/description.ts'

/** The grid the theme rendered, and the message shown in its place when nothing matched. */
export class ResultsView {
    #contract: Contract
    #cardView: CardView

    constructor(contract: Contract) {
        this.#contract = contract
        this.#cardView = new CardView(contract)
    }

    show(cards: Card[], pageWindow: PageWindow) {
        const list = this.#contract.one('results')
        const template = this.#contract.one('card-template')

        if (!(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
            return
        }

        list.replaceChildren(...cards.flatMap((card) => this.#card(template, card)))
        list.hidden = cards.length === 0
        this.#showEmpty(cards, pageWindow)
    }

    #showEmpty(cards: Card[], pageWindow: PageWindow) {
        const empty = this.#contract.one('empty')
        const noResults = this.#contract.one('no-results')
        const pastTheEnd = this.#contract.one('past-the-end')

        if (empty instanceof HTMLElement) {
            empty.hidden = cards.length > 0
        }

        if (noResults instanceof HTMLElement && pastTheEnd instanceof HTMLElement) {
            noResults.hidden = pageWindow.isPastTheEnd
            pastTheEnd.hidden = !pageWindow.isPastTheEnd
        }
    }

    #card(template: HTMLTemplateElement, card: Card) {
        const node = template.content.firstElementChild?.cloneNode(true)

        return node instanceof Element ? [this.#cardView.show(node, card)] : []
    }
}
