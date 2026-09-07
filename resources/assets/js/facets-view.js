import { countLabel } from './facet-counts.js'

/**
 * @import { Contract } from './contract.js'
 * @import { ListingDescription } from './description.js'
 * @import { FacetCounts } from './facet-counts.js'
 * @import { ListingState } from './listing-state.js'
 *
 * @typedef {{ host: HTMLElement, input: HTMLInputElement, label: Element | null, taxonomy: string }} Box
 */

/** The boxes and their counts. */
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
        const distributions = new Map(
            this.#description.facets.map((facet) => [facet.taxonomy, counts.of(facet)])
        )

        for (const box of this.#boxes()) {
            this.#showCount(box, distributions.get(box.taxonomy)?.[box.input.value] ?? 0)
        }
    }

    /**
     * @param {Box} box
     * @param {number} hits
     */
    #showCount({ host, label }, hits) {
        if (label) {
            label.textContent = countLabel(this.#description.countPattern, hits)
        }

        host.hidden = hits === 0
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
