import { CardView } from '../results/card-view.ts'
import { CountLabel } from '../shared/count-label.ts'
import { Highlight } from './highlight.ts'

import type { Contract } from '../shared/contract.ts'
import type { SiteSearchDescription } from '../shared/description.ts'
import type { SearchAnswer, SearchHit } from '../shared/search-client.ts'
import type { SearchedSection } from './site-search-query.ts'

const TYPE_ATTRIBUTE = 'data-type'
const LIMIT_ATTRIBUTE = 'data-limit'

export interface SectionCount {
    heading: string
    total: number
}

interface SectionSetup extends SearchedSection {
    countLabel: CountLabel
    countPattern: string
}

/** One post type's section: its count and its cards, the whole of it hidden while its type found nothing. */
export class SectionView {
    #contract: Contract
    #section: HTMLElement
    #setup: SectionSetup
    #cardView: CardView
    #highlight: Highlight
    #total = 0

    constructor(contract: Contract, section: HTMLElement, setup: SectionSetup) {
        this.#contract = contract
        this.#section = section
        this.#setup = setup
        this.#cardView = new CardView(contract)
        this.#highlight = new Highlight(contract)
    }

    static allIn(contract: Contract, description: SiteSearchDescription) {
        const countLabel = new CountLabel(description.locale)
        const { countPattern } = description

        return contract.all('search-section').flatMap((section) => {
            const postType = section.getAttribute(TYPE_ATTRIBUTE) ?? ''
            const type = description.types.find((described) => described.postType === postType)

            if (type === undefined || !(section instanceof HTMLElement)) {
                console.error(`[meilifacets] the search "${description.name}" describes no type "${postType}".`)

                return []
            }

            const limit = SectionView.#limitOf(section, description.limit)

            return [new SectionView(contract, section, { type, limit, countLabel, countPattern })]
        })
    }

    static #limitOf(section: Element, fallback: number) {
        const limit = Number(section.getAttribute(LIMIT_ATTRIBUTE))

        return Number.isInteger(limit) && limit > 0 ? limit : fallback
    }

    get searched(): SearchedSection {
        return { type: this.#setup.type, limit: this.#setup.limit }
    }

    get count(): SectionCount {
        return { heading: this.#setup.type.heading, total: this.#total }
    }

    get options() {
        const results = this.#results()

        if (this.#section.hidden || results === null) {
            return []
        }

        return this.#contract.all('card', results).filter((option) => option instanceof HTMLElement)
    }

    show({ hits = [], totalHits = hits.length }: SearchAnswer) {
        const results = this.#results()
        const template = this.#contract.one('search-card-template', this.#section)

        if (results === null || !(template instanceof HTMLTemplateElement)) {
            return
        }

        results.replaceChildren(...hits.flatMap((hit) => this.#card(template, hit)))
        this.#total = hits.length === 0 ? 0 : totalHits
        this.#writeCount()
        this.#section.hidden = hits.length === 0
    }

    hide() {
        this.#total = 0
        this.#section.hidden = true
    }

    #writeCount() {
        const count = this.#contract.one('search-count', this.#section)

        if (count !== null) {
            count.textContent = this.#setup.countLabel.of(this.#setup.countPattern, this.#total)
        }
    }

    #results() {
        return this.#contract.one('search-results', this.#section)
    }

    #card(template: HTMLTemplateElement, hit: SearchHit) {
        const node = template.content.firstElementChild?.cloneNode(true)

        if (!(node instanceof Element)) {
            return []
        }

        this.#cardView.show(node, hit.card ?? {})
        this.#highlight.show(node, hit._formatted?.card)

        return [node]
    }
}
