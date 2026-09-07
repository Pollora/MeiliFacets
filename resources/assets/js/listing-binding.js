import { Contract } from './contract.js'
import { FacetCounts } from './facet-counts.js'
import { FacetsView } from './facets-view.js'
import { FilterSummaryView } from './filter-summary-view.js'
import { ListingQuery } from './listing-query.js'
import { PaginationView } from './pagination-view.js'
import { ResultsView } from './results-view.js'
import { SortCombobox } from './sort-combobox.js'

/**
 * @import { ListingDescription } from './description.js'
 * @import { Listing } from './listing.js'
 * @import { ListingState } from './listing-state.js'
 */

/** Ties the theme's markup to the listing: it listens on the root, and reads hooks, never classes. */
export class ListingBinding {
    /** @type {Element} */
    #root

    /** @type {Listing} */
    #listing

    /** @type {ResultsView} */
    #results

    /** @type {FacetsView} */
    #facets

    /** @type {PaginationView} */
    #pagination

    /** @type {SortCombobox} */
    #sort

    /** @type {FilterSummaryView} */
    #summary

    /**
     * @param {Element} root
     * @param {Contract} contract
     * @param {Listing} listing
     * @param {ListingDescription} description
     */
    constructor(root, contract, listing, description) {
        this.#root = root
        this.#listing = listing
        this.#results = new ResultsView(contract)
        this.#facets = new FacetsView(contract, description)
        this.#pagination = new PaginationView(contract, description)
        this.#sort = new SortCombobox(contract, (sort) => this.#listing.sortBy(sort))
        this.#summary = new FilterSummaryView(contract, description)
    }

    start() {
        this.#root.addEventListener('change', (event) => this.#ticked(event))
        this.#root.addEventListener('click', (event) => this.#clicked(event))
        this.#listing.addEventListener('change', (event) => this.#moved(/** @type {CustomEvent} */ (event).detail))
        this.#listing.addEventListener('results', (event) => this.#repaint(/** @type {CustomEvent} */ (event).detail))
        this.#listing.listenToHistory()
        this.#sort.start()

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

        const taxonomy = this.#facets.taxonomyOf(input)

        if (taxonomy !== undefined) {
            this.#listing.toggle(taxonomy, input.value)
        }
    }

    /**
     * @param {Event} event
     */
    #clicked(event) {
        if (this.#acted(event)) {
            this.#reveal(event)
        }
    }

    /**
     * Whether the click was one of the gestures that replace the grid. The sort
     * is picked inside the combobox, so only its choice is seen here.
     *
     * @param {Event} event
     */
    #acted(event) {
        if (this.#hookOf(event.target, 'apply')) {
            void this.#listing.apply()

            return true
        }

        if (this.#hookOf(event.target, 'reset')) {
            this.#listing.reset()

            return true
        }

        if (this.#hookOf(event.target, 'sort-option')) {
            return true
        }

        const page = this.#pageOf(event.target)

        if (page === null) {
            return false
        }

        this.#listing.goToPage(page)

        return true
    }

    /**
     * @param {Event} event
     */
    #reveal(event) {
        // `detail` is 0 on a click the keyboard raised, and non-zero on a real one.
        if (/** @type {MouseEvent} */ (event).detail > 0) {
            // No `behavior`: the theme's `scroll-behavior` decides, reduced-motion guard included.
            this.#root.scrollIntoView({ block: 'start' })
        }
    }

    /**
     * @param {EventTarget | null} target
     * @returns {number | null}
     */
    #pageOf(target) {
        const button = this.#hookOf(target, 'page')
            ?? this.#hookOf(target, 'previous')
            ?? this.#hookOf(target, 'next')

        return button instanceof HTMLButtonElement ? Number.parseInt(button.value, 10) || null : null
    }

    /**
     * @param {{ state: ListingState }} detail
     */
    #moved({ state }) {
        this.#facets.showSelection(state)
        this.#sort.show(state)
        this.#summary.show(state)
    }

    /**
     * @param {{ answers: Record<string, any>, state: ListingState }} detail
     */
    #repaint({ answers, state }) {
        const results = answers[ListingQuery.RESULTS] ?? {}

        this.#results.show((results.hits ?? []).map((/** @type {any} */ hit) => hit.card ?? {}))
        this.#facets.showCounts(new FacetCounts(answers))
        this.#pagination.show(state, results.totalHits ?? 0)
    }

    /**
     * @param {EventTarget | null} target
     * @param {string} hook
     */
    #hookOf(target, hook) {
        return target instanceof Element ? target.closest(Contract.selector(hook)) : null
    }
}
