import { Contract } from '../shared/contract.ts'
import { FacetCounts } from '../facets/facet-counts.ts'
import { FacetsView } from '../facets/facets-view.ts'
import { FilterSummaryView } from './filter-summary-view.ts'
import { PageWindow } from '../pagination/page-window.ts'
import { PaginationView } from '../pagination/pagination-view.ts'
import { PriceControl } from '../price/price-control.ts'
import { ResultsView } from '../results/results-view.ts'
import { RESULTS } from '../shared/plan.ts'
import { SortCombobox } from '../sort/sort-combobox.ts'
import { SortQuery } from '../sort/sort-query.ts'
import { TotalView } from './total-view.ts'
import { ActiveValuesView } from './active-values-view.ts'
import { SelectionCountView } from './selection-count-view.ts'
import { DisclosureGroup } from '../collapsible/disclosure-group.ts'
import { SelectedCountView } from '../collapsible/selected-count-view.ts'

import type { ListingDescription } from '../shared/description.ts'
import type { ListingState } from './listing-state.ts'
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
    #price: PriceControl
    #summary: FilterSummaryView
    #selectionCount: SelectionCountView
    #selectedCount: SelectedCountView
    #disclosures: DisclosureGroup
    #total: TotalView
    #activeValues: ActiveValuesView
    #sortQuery: SortQuery

    constructor(contract: Contract, listing: Listing, description: ListingDescription) {
        this.#root = contract.root
        this.#listing = listing
        this.#description = description
        this.#results = new ResultsView(contract)
        this.#facets = new FacetsView(contract, description)
        this.#pagination = new PaginationView(contract)
        this.#sort = new SortCombobox(contract, (sort) => this.#listing.sortBy(sort))
        this.#sortQuery = new SortQuery(description.sortFilters)
        this.#summary = new FilterSummaryView(contract, description)
        this.#selectionCount = new SelectionCountView(contract)
        this.#selectedCount = new SelectedCountView(contract, this.#facets)
        this.#disclosures = new DisclosureGroup(contract)
        this.#total = new TotalView(contract, description)
        this.#activeValues = new ActiveValuesView(contract, description, listing)
        this.#price = new PriceControl(contract, description, (min, max) => this.#listing.priceBetween(min, max))
    }

    start() {
        this.#root.addEventListener('change', (event) => this.#ticked(event))
        this.#root.addEventListener('click', (event) => this.#clicked(event))
        this.#listing.addEventListener('change', (event) => this.#moved((event as CustomEvent<ChangeDetail>).detail))
        this.#listing.addEventListener('results', (event) => this.#repaint((event as CustomEvent<ResultsDetail>).detail))
        this.#listing.listenToHistory()
        this.#sort.start()
        this.#price.start()
        this.#activeValues.start()
        this.#disclosures.start()

        return this
    }

    #ticked(event: Event) {
        const input = this.#hookOf(event.target, 'input')

        if (!(input instanceof HTMLInputElement)) {
            return
        }

        const taxonomy = this.#facets.taxonomyOf(input)

        if (taxonomy !== undefined) {
            this.#listing.toggle(taxonomy, input.value)
        }
    }

    #clicked(event: Event) {
        const more = this.#hookOf(event.target, 'more')

        if (more !== null) {
            this.#facets.toggleFold(more)

            return
        }

        if (this.#acted(event)) {
            this.#reveal(event)
        }
    }

    /**
     * Whether the click was one of the gestures that replace the grid. The sort
     * is picked inside the combobox, so only its choice is seen here.
     */
    #acted(event: Event) {
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
        this.#price.show(state)
        this.#summary.show(state)
        this.#selectionCount.show(state)
        this.#selectedCount.show(state)
    }

    #repaint({ answers, state }: ResultsDetail) {
        const results = answers[RESULTS] ?? {}
        const pageWindow = this.#pageWindowOf(state, results.totalHits ?? 0)

        this.#results.show((results.hits ?? []).map((hit) => hit.card ?? {}), pageWindow)
        this.#facets.showCounts(new FacetCounts(answers))
        this.#price.showBounds(answers, state)
        this.#sort.showMatches(this.#sortQuery.matchesIn(results.facetDistribution ?? {}), state)
        this.#pagination.show(pageWindow)
        this.#total.show(results.totalHits ?? 0)
        this.#activeValues.show(state)
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
