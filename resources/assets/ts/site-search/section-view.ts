import { CardView } from '../results/card-view.ts'
import { LEAVING } from '../shared/attributes.ts'
import { CountLabel } from '../shared/count-label.ts'
import { Highlight } from './highlight.ts'

import type { Contract } from '../shared/contract.ts'
import type { SiteSearchDescription } from '../shared/description.ts'
import type { SearchAnswer, SearchHit } from '../shared/search-client.ts'
import type { SearchedSection } from './site-search-query.ts'

const TYPE_ATTRIBUTE = 'data-type'
const LIMIT_ATTRIBUTE = 'data-limit'
const TABINDEX = 'tabindex'
const OUT_OF_TAB_ORDER = '-1'

export interface SectionCount {
    heading: string
    total: number
}

interface SectionSetup extends SearchedSection {
    countLabel: CountLabel
    countPattern: string
}

/**
 * One post type's section: its count and its cards, the whole of it hidden while its type found nothing.
 * A card another term finds again keeps its node, only its words rewritten: the eye follows it from one
 * answer to the next.
 */
export class SectionView {
    #contract: Contract
    #section: HTMLElement
    #setup: SectionSetup
    #cardView: CardView
    #highlight: Highlight
    #total = 0
    /** By document identity, in the order shown; a hit with no identity gets a key nothing else matches. */
    #cards = new Map<unknown, Element>()
    /** What each card was last drawn from: a card found again with the same words is not written again. */
    #drawn = new WeakMap<Element, string>()

    constructor(contract: Contract, section: HTMLElement, setup: SectionSetup) {
        this.#contract = contract
        this.#section = section
        this.#setup = setup
        this.#cardView = CardView.withDecorativeImages(contract)
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

    get listbox() {
        return this.#results()
    }

    get options() {
        if (this.#section.hidden) {
            return []
        }

        return [...this.#cards.values()].filter((option) => option instanceof HTMLElement)
    }

    show({ hits = [], totalHits = hits.length }: SearchAnswer) {
        const results = this.#results()
        const template = this.#contract.one('search-card-template', this.#section)

        if (results === null || !(template instanceof HTMLTemplateElement)) {
            return
        }

        if (hits.length === 0) {
            this.hide()

            return
        }

        this.#reconcile(results, template, hits)
        this.#total = totalHits
        this.#writeCount()
        this.#section.hidden = false
    }

    /** The cards stay in place, out of sight: the next answer finds them again. */
    hide() {
        this.#total = 0
        this.#section.hidden = true
    }

    #reconcile(results: Element, template: HTMLTemplateElement, hits: SearchHit[]) {
        const cards = new Map(hits.flatMap((hit) => this.#cardFor(template, hit)))

        this.#cards.forEach((card, key) => cards.has(key) || card.remove())
        this.#arrange(results, [...cards.values()])
        this.#cards = cards
    }

    #cardFor(template: HTMLTemplateElement, hit: SearchHit): [unknown, Element][] {
        const key = hit.ID ?? Symbol('unidentified')
        const kept = this.#cards.get(key)

        if (kept !== undefined) {
            this.#redraw(kept, hit)

            return [[key, kept]]
        }

        return this.#cardView.stamp(template, hit.card ?? {}).map((card) => {
            this.#takeLinksOutOfTabOrder(card)
            this.#highlight.show(card, hit._formatted?.card)
            this.#drawn.set(card, this.#wordsOf(hit))

            return [key, card]
        })
    }

    #takeLinksOutOfTabOrder(card: Element) {
        this.#contract.all('url', card).forEach((link) => link.setAttribute(TABINDEX, OUT_OF_TAB_ORDER))
    }

    #redraw(card: Element, hit: SearchHit) {
        const words = this.#wordsOf(hit)

        if (this.#drawn.get(card) !== words) {
            this.#cardView.showWords(card, hit.card ?? {})
            this.#highlight.show(card, hit._formatted?.card)
            this.#drawn.set(card, words)
        }
    }

    #wordsOf({ card = {}, _formatted: formatted = {} }: SearchHit) {
        return JSON.stringify([card.title, card.summary, formatted.card?.title, formatted.card?.summary])
    }

    /** Moves only what is out of place: a node moved is a node the browser restyles. */
    #arrange(results: Element, cards: Element[]) {
        let cursor = this.#staying(results.firstElementChild)

        for (const card of cards) {
            if (card === cursor) {
                cursor = this.#staying(cursor.nextElementSibling)
            } else {
                results.insertBefore(card, cursor)
            }
        }
    }

    #staying(node: Element | null): Element | null {
        return node?.hasAttribute(LEAVING) ? this.#staying(node.nextElementSibling) : node
    }

    #writeCount() {
        const count = this.#contract.one('search-count', this.#section)

        const text = this.#setup.countLabel.of(this.#setup.countPattern, this.#total)

        if (count !== null && count.textContent !== text) {
            count.textContent = text
        }
    }

    #results() {
        return this.#contract.one('search-results', this.#section)
    }
}
