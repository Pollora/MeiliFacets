import { CardPainter } from './card-painter.js'
import { Contract } from './contract.js'
import { countLabel, FacetCounts } from './facet-counts.js'
import { ListingQuery } from './listing-query.js'

/**
 * @import { ListingDescription } from './description.js'
 * @import { Listing } from './listing.js'
 */

/**
 * Ties the markup the theme rendered to the listing. It listens on the root, so
 * a card cloned after the fact needs no wiring of its own, and it never reads a
 * class: the theme owns those.
 */
export class ListingBinding {
    /** @type {Element} */
    #root

    /** @type {Contract} */
    #contract

    /** @type {Listing} */
    #listing

    /** @type {ListingDescription} */
    #description

    /** @type {CardPainter} */
    #painter

    /** @type {Map<string, string>} */
    #taxonomies

    /**
     * @param {Element} root
     * @param {Contract} contract
     * @param {Listing} listing
     * @param {ListingDescription} description
     */
    constructor(root, contract, listing, description) {
        this.#root = root
        this.#contract = contract
        this.#listing = listing
        this.#description = description
        this.#painter = new CardPainter(contract)
        this.#taxonomies = new Map(Object.entries(description.params).map(([taxonomy, name]) => [name, taxonomy]))
    }

    start() {
        this.#root.addEventListener('change', (event) => this.#ticked(event))
        this.#root.addEventListener('click', (event) => this.#clicked(event))
        this.#listing.addEventListener('results', (event) => this.#repaint(event.detail.answers))
        this.#listing.listenToHistory()

        return this
    }

    /**
     * @param {Event} event
     */
    #ticked(event) {
        const input = this.#hookOf(event.target, 'input')

        if (!(input instanceof HTMLInputElement)) {
            return
        }

        const taxonomy = this.#taxonomies.get(input.name)

        if (taxonomy !== undefined) {
            this.#listing.toggle(taxonomy, input.value)
        }
    }

    /**
     * @param {Event} event
     */
    #clicked(event) {
        if (this.#hookOf(event.target, 'apply')) {
            void this.#listing.apply()
        }
    }

    /**
     * @param {Record<string, any>} answers
     */
    #repaint(answers) {
        const hits = answers[ListingQuery.RESULTS]?.hits ?? []

        this.#showResults(hits.map((/** @type {any} */ hit) => hit.card ?? {}))
        this.#showCounts(new FacetCounts(answers))
    }

    /**
     * The grid is replaced whole: nobody holds the focus inside it while a
     * filter is being applied.
     *
     * @param {Record<string, unknown>[]} cards
     */
    #showResults(cards) {
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

    /**
     * Values are stable nodes: the focus is on the box that was just ticked, and
     * recreating them would throw a keyboard visitor out of the list.
     *
     * @param {FacetCounts} counts
     */
    #showCounts(counts) {
        for (const facet of this.#description.facets) {
            const distribution = counts.of(facet)

            for (const value of this.#contract.all('facet-value')) {
                this.#showCount(value, facet.taxonomy, distribution)
            }
        }
    }

    /**
     * @param {Element} value
     * @param {string} taxonomy
     * @param {Record<string, number>} distribution
     */
    #showCount(value, taxonomy, distribution) {
        const input = this.#contract.one('input', value)

        if (!(input instanceof HTMLInputElement) || this.#taxonomies.get(input.name) !== taxonomy) {
            return
        }

        const count = distribution[input.value] ?? 0
        const label = this.#contract.one('count', value)

        if (label) {
            label.textContent = countLabel(this.#description.countPattern, count)
        }

        if (value instanceof HTMLElement) {
            value.hidden = count === 0
        }
    }

    /**
     * @param {EventTarget | null} target
     * @param {string} hook
     */
    #hookOf(target, hook) {
        return target instanceof Element ? target.closest(Contract.selector(hook)) : null
    }
}
