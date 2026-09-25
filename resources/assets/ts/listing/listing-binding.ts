import { Contract } from '../shared/contract.ts'
import { FacetCounts } from '../facets/facet-counts.ts'
import { FacetsView } from '../facets/facets-view.ts'
import { PageWindow } from '../pagination/page-window.ts'
import { PaginationView } from '../pagination/pagination-view.ts'
import { PriceControl } from '../price/price-control.ts'
import { ResultsView } from '../results/results-view.ts'
import { RESULTS } from '../shared/plan.ts'
import { SortCombobox } from '../sort/sort-combobox.ts'
import { SortQuery } from '../sort/sort-query.ts'
import { SortRadios } from '../sort/sort-radios.ts'
import { DisclosureGroup } from '../collapsible/disclosure-group.ts'
import { ListingDrawers } from '../drawer/listing-drawers.ts'
import { ResetFocus } from './reset-focus.ts'
import { SummaryBinding } from './summary-binding.ts'

import type { ListingDescription } from '../shared/description.ts'
import type { ListingState } from './listing-state.ts'
import type { SearchAnswer } from '../shared/search-client.ts'
import type { ChangeDetail, Listing, ResultsDetail } from './listing.ts'

/** Ties the theme's markup to the listing: it listens on the root, and reads hooks, never classes. */
export class ListingBinding {
    #root: Element
    #listing: Listing
    #description: ListingDescription
    #results: ResultsView
    #facets: FacetsView
    #pagination: PaginationView
    #sort: SortCombobox
    #sortRadios: SortRadios
    #price: PriceControl
    #summary: SummaryBinding
    #disclosures: DisclosureGroup
    #sortQuery: SortQuery
    #drawers: ListingDrawers
    #resetFocus: ResetFocus

    constructor(contract: Contract, listing: Listing, description: ListingDescription) {
        this.#root = contract.root
        this.#listing = listing
        this.#description = description
        this.#results = new ResultsView(contract)
        this.#facets = new FacetsView(contract, description)
        this.#pagination = new PaginationView(contract)
        this.#sort = new SortCombobox(contract, (sort) => this.#listing.sortBy(sort))
        this.#sortRadios = new SortRadios(contract, (sort) => this.#listing.sortBy(sort))
        this.#sortQuery = new SortQuery(description.sortFilters)
        this.#price = new PriceControl(contract, description, (min, max) => this.#listing.priceBetween(min, max))
        this.#summary = new SummaryBinding(contract, description, { listing, holders: [this.#facets, this.#price] })
        this.#disclosures = new DisclosureGroup(contract, () => this.#facets.refold())
        this.#drawers = new ListingDrawers(contract, this.#disclosures)
        this.#resetFocus = new ResetFocus(contract)
    }

    start() {
        this.#root.addEventListener('change', (event) => this.#ticked(event))
        this.#root.addEventListener('click', (event) => this.#clicked(event))
        this.#listing.addEventListener('change', (event) => this.#moved((event as CustomEvent<ChangeDetail>).detail))
        this.#listing.addEventListener('results', (event) => this.#repaint((event as CustomEvent<ResultsDetail>).detail))
        this.#listing.listenToHistory()
        this.#sort.start()
        this.#sortRadios.start()
        this.#price.start()
        this.#summary.start()
        this.#disclosures.start()
        this.#drawers.start()

        return this
    }

    #ticked(event: Event) {
        const input = this.#hookOf(event.target, 'input')

        if (!(input instanceof HTMLInputElement)) {
            return
        }

        if (this.#facets.refuses(input)) {
            input.checked = false

            return
        }

        const taxonomy = this.#facets.taxonomyOf(input)

        if (taxonomy !== undefined) {
            this.#listing.toggle(taxonomy, input.value)
        }
    }

    #clicked(event: Event) {
        if (this.#refused(event)) {
            return
        }

        const more = this.#hookOf(event.target, 'more')

        if (more !== null) {
            this.#facets.toggleFold(more)

            return
        }

        if (this.#acted(event)) {
            this.#reveal(event)
        }
    }

    /** Cancelling the click unticks the box before any `change` is fired. */
    #refused(event: Event) {
        const input = this.#hookOf(event.target, 'input')
        const refused = input instanceof HTMLInputElement && this.#facets.refuses(input)

        if (refused) {
            event.preventDefault()
        }

        return refused
    }

    /**
     * Whether the click was one of the gestures that replace the grid. The sort
     * is picked inside the combobox, so only its choice is seen here.
     */
    #acted(event: Event) {
        if (this.#hookOf(event.target, 'apply')) {
            this.#applied()

            return true
        }

        if (this.#resetFrom(event.target)) {
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

    #resetFrom(target: EventTarget | null) {
        const reset = this.#hookOf(target, 'reset')

        if (reset !== null) {
            this.#listing.reset()
            this.#resetFocus.landFrom(reset)
        }

        return reset !== null
    }

    #applied() {
        if (!this.#listing.searchesAtOnce) {
            void this.#listing.apply()
        }
    }

    #reveal(event: Event) {
        const asked = event.target instanceof Element && event.target.closest(Contract.SCROLL) !== null

        // `detail` is 0 on a click the keyboard raised, and non-zero on a real one.
        if (asked && (event as MouseEvent).detail > 0) {
            // No `behavior`: the theme's `scroll-behavior` decides, reduced-motion guard included.
            this.#root.scrollIntoView({ block: 'start' })
        }
    }

    #pageOf(target: EventTarget | null): number | null {
        const button = this.#hookOf(target, 'page')
            ?? this.#hookOf(target, 'previous')
            ?? this.#hookOf(target, 'next')

        return button instanceof HTMLButtonElement ? Number.parseInt(button.value, 10) || null : null
    }

    #moved({ state }: ChangeDetail) {
        this.#facets.showSelection(state)
        this.#sort.show(state)
        this.#sortRadios.show(state)
        this.#price.show(state)
        this.#summary.showHeld(state)
    }

    #repaint({ answers, state }: ResultsDetail) {
        const results = answers[RESULTS] ?? {}

        this.#facets.showCounts(new FacetCounts(answers))
        this.#price.showBounds(answers, state)
        const matches = this.#sortQuery.matchesIn(results.facetDistribution ?? {})

        this.#sort.showMatches(matches, state)
        this.#sortRadios.showMatches(matches, state)
        this.#summary.showAnswered(results.totalHits ?? 0, state)
        this.#drawers.paintPage(() => this.#repaintGrid(results, state))
    }

    #repaintGrid(results: SearchAnswer, state: ListingState) {
        const pageWindow = this.#pageWindowOf(state, results.totalHits ?? 0)

        this.#results.show((results.hits ?? []).map((hit) => hit.card ?? {}), pageWindow)
        this.#pagination.show(pageWindow)
    }

    #pageWindowOf(state: ListingState, total: number) {
        return new PageWindow({
            asked: state.page,
            perPage: this.#description.perPage,
            total,
            reachable: this.#description.reachableHits,
        })
    }

    #hookOf(target: EventTarget | null, hook: string) {
        return target instanceof Element ? target.closest(Contract.selector(hook)) : null
    }
}
