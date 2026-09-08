import { Contract } from './contract.js'
import { countLabel } from './facet-counts.js'

/**
 * @import { ListingDescription } from './description.js'
 * @import { FacetCounts } from './facet-counts.js'
 * @import { ListingState } from './listing-state.js'
 *
 * @typedef {{ host: HTMLElement, input: HTMLInputElement, label: Element | null, taxonomy: string }} Box
 */

export class FacetsView {
    /** @type {Contract} */
    #contract

    /** @type {ListingDescription} */
    #description

    /** @type {Map<string, string>} */
    #taxonomies

    /**
     * Facet values are never recreated: the same nodes answer for the life of the page.
     *
     * @type {Box[] | null}
     */
    #boxed = null

    /** @type {Map<string, Box[]> | null} */
    #grouped = null

    /** @type {Map<string, Element> | null} */
    #blocked = null

    /** @type {Map<string, Element> | null} */
    #buttoned = null

    /** @type {Set<string>} */
    #unfolded = new Set()

    /** @type {Map<Element, boolean>} */
    #hasHits = new Map()

    /**
     * @param {Contract} contract
     * @param {ListingDescription} description
     */
    constructor(contract, description) {
        this.#contract = contract
        this.#description = description
        this.#taxonomies = new Map(Object.entries(description.params).map(([taxonomy, name]) => [name, taxonomy]))
    }

    /**
     * @param {HTMLInputElement} input
     * @returns {string | undefined}
     */
    taxonomyOf(input) {
        return this.#taxonomies.get(input.name)
    }

    /**
     * @param {ListingState} state
     */
    showSelection(state) {
        for (const { input, taxonomy } of this.#boxes()) {
            input.checked = state.selected(taxonomy).includes(input.value)
        }
    }

    /**
     * @param {FacetCounts} counts
     */
    showCounts(counts) {
        for (const facet of this.#description.facets) {
            const distribution = counts.of(facet)

            for (const box of this.#boxesOf(facet.taxonomy)) {
                const hits = distribution[box.input.value] ?? 0

                this.#showCount(box, hits)
                this.#hasHits.set(box.host, hits > 0)
            }
        }

        this.#showFolds()
    }

    /**
     * @param {Element} button
     */
    toggleFold(button) {
        const taxonomy = this.#taxonomyIn(button)

        if (taxonomy === undefined) {
            return
        }

        if (this.#unfolded.has(taxonomy)) {
            this.#unfolded.delete(taxonomy)
        } else {
            this.#unfolded.add(taxonomy)
        }

        this.#showFolds()
    }

    /** A value is read when it is held, or when it still has results and the fold has room for it. */
    #showFolds() {
        for (const facet of this.#description.facets) {
            const expanded = this.#unfolded.has(facet.taxonomy)
            let room = facet.visible
            let read = 0

            for (const { host, input } of this.#boxesOf(facet.taxonomy)) {
                const counted = this.#hasHits.get(host) ?? true

                host.hidden = ! input.checked && (! counted || (! expanded && room <= 0))
                room -= counted ? 1 : 0
                read += host.hidden ? 0 : 1
            }

            this.#showBlock(facet.taxonomy, read > 0)
            this.#showFoldButton(facet.taxonomy, expanded, room < 0)
        }
    }

    /**
     * @param {string} taxonomy
     * @param {boolean} readable
     */
    #showBlock(taxonomy, readable) {
        const block = this.#blocks().get(taxonomy)

        if (block instanceof HTMLElement) {
            block.hidden = ! readable
        }
    }

    /**
     * @param {string} taxonomy
     * @param {boolean} expanded
     * @param {boolean} foldable
     */
    #showFoldButton(taxonomy, expanded, foldable) {
        const button = this.#buttons().get(taxonomy)

        if (!(button instanceof HTMLElement)) {
            return
        }

        button.hidden = ! foldable

        // Rewriting `aria-expanded` unchanged makes some screen readers announce the button again.
        if (button.getAttribute('aria-expanded') !== String(expanded)) {
            button.textContent = this.#description.foldLabels[expanded ? 'less' : 'more']
            button.setAttribute('aria-expanded', String(expanded))
        }
    }

    /**
     * @param {string} taxonomy
     * @returns {Box[]}
     */
    #boxesOf(taxonomy) {
        if (this.#grouped === null) {
            this.#grouped = new Map(this.#description.facets.map((facet) => [facet.taxonomy, []]))

            for (const box of this.#boxes()) {
                this.#grouped.get(box.taxonomy)?.push(box)
            }
        }

        return this.#grouped.get(taxonomy) ?? []
    }

    /**
     * @returns {Map<string, Element>}
     */
    #blocks() {
        return this.#blocked ??= new Map(this.#contract.all('facet').flatMap((block) => {
            const taxonomy = this.#taxonomyIn(block)

            return taxonomy === undefined ? [] : [[taxonomy, block]]
        }))
    }

    /**
     * @returns {Map<string, Element>}
     */
    #buttons() {
        return this.#buttoned ??= new Map([...this.#blocks()].flatMap(([taxonomy, block]) => {
            const button = this.#contract.one('more', block)

            return button === null ? [] : [[taxonomy, button]]
        }))
    }

    /**
     * A block names no taxonomy of its own: the boxes it holds name it for it.
     *
     * @param {Element | null} node
     * @returns {string | undefined}
     */
    #taxonomyIn(node) {
        const block = node?.closest(Contract.selector('facet')) ?? null
        const input = block === null ? null : this.#contract.one('input', block)

        return input instanceof HTMLInputElement ? this.taxonomyOf(input) : undefined
    }

    /**
     * @param {Box} box
     * @param {number} hits
     */
    #showCount({ label }, hits) {
        if (label) {
            label.textContent = countLabel(this.#description.countPattern, hits)
        }
    }

    /**
     * A value the listing does not declare is markup the theme added: left alone.
     *
     * @returns {Box[]}
     */
    #boxes() {
        return this.#boxed ??= this.#contract.all('facet-value').flatMap((host) => this.#box(host))
    }

    /**
     * @param {Element} host
     * @returns {Box[]}
     */
    #box(host) {
        const input = this.#contract.one('input', host)

        if (!(host instanceof HTMLElement) || !(input instanceof HTMLInputElement)) {
            return []
        }

        const taxonomy = this.taxonomyOf(input)

        return taxonomy === undefined ? [] : [{ host, input, label: this.#contract.one('count', host), taxonomy }]
    }
}
