import { Range } from '../shared/range.ts'

import type { FacetDescription, StateDescription } from '../shared/description.ts'

export const FIRST_PAGE = 1

const MAX_QUERY_LENGTH = 200

const VALUE_SEPARATOR = ','

export type StateChanges = Partial<Omit<StateDescription, 'price'>> & { price?: Partial<StateDescription['price']> }

/** What the visitor has asked for, never edited in place: every gesture hands back a new value. */
export class ListingState {
    #facets: Readonly<Record<string, readonly string[]>>
    #query: string
    #sort: string | null
    #page: number
    #price: Range

    constructor({ facets = {}, query = '', sort = null, page = FIRST_PAGE, price = {} }: StateChanges = {}) {
        this.#facets = Object.freeze(Object.fromEntries(
            Object.entries(facets)
                .map(([taxonomy, values]) => [taxonomy, Object.freeze(ListingState.#tidy(values))] as const)
                .filter(([, values]) => values.length > 0)
        ))
        // Cut by code point, like `mb_substr()`: slicing UTF-16 units would cut a surrogate pair in half.
        this.#query = [...query].slice(0, MAX_QUERY_LENGTH).join('')
        this.#sort = sort
        this.#page = Math.max(Math.trunc(page) || FIRST_PAGE, FIRST_PAGE)
        this.#price = new Range(ListingState.#bound(price.min), ListingState.#bound(price.max))
    }

    static #bound(value: number | null | undefined) {
        // `-0` would reach the engine as `>= -0`, which it does not read as `>= 0`.
        return typeof value === 'number' && Number.isFinite(value) && value >= 0 ? value + 0 : null
    }

    get facets() {
        return this.#facets
    }

    get query() {
        return this.#query
    }

    get sort() {
        return this.#sort
    }

    get page() {
        return this.#page
    }

    get price() {
        return this.#price
    }

    pricedBetween(min: number | null, max: number | null) {
        return this.with({ price: { min, max }, page: FIRST_PAGE })
    }

    selected(taxonomy: string): readonly string[] {
        return this.#facets[taxonomy] ?? []
    }

    /** Mirrors ListingState on the server: a range counts once, whatever its ends. */
    activeFilterCount() {
        const ticked = Object.values(this.#facets).reduce((total, values) => total + values.length, 0)

        return ticked + this.priceFilterCount()
    }

    priceFilterCount() {
        return this.#price.isEmpty() ? 0 : 1
    }

    isPristine() {
        return Object.keys(this.#facets).length === 0
            && this.#query === ''
            && this.#sort === null
            && this.#page === FIRST_PAGE
            && this.#price.isEmpty()
    }

    toggling(facet: FacetDescription, value: string) {
        const held = value.trim()

        if (held === '') {
            return this
        }

        const next = this.#toggled(facet, this.selected(facet.taxonomy), held)

        return this.with({ facets: { ...this.#facets, [facet.taxonomy]: next }, page: FIRST_PAGE })
    }

    #toggled(facet: FacetDescription, held: readonly string[], value: string) {
        if (held.includes(value)) {
            return held.filter((current) => current !== value)
        }

        return facet.multiple ? [...held, value].slice(-facet.cap) : [value]
    }

    without(taxonomy: string, value: string) {
        const held = this.selected(taxonomy).filter((current) => current !== value)

        return this.with({ facets: { ...this.#facets, [taxonomy]: held }, page: FIRST_PAGE })
    }

    sortedBy(sort: string | null) {
        return this.with({ sort, page: FIRST_PAGE })
    }

    searching(query: string) {
        return this.with({ query: query.trim(), page: FIRST_PAGE })
    }

    onPage(page: number) {
        return this.with({ page })
    }

    cleared() {
        return new ListingState()
    }

    with(changes: StateChanges) {
        return new ListingState({
            facets: this.#facets,
            query: this.#query,
            sort: this.#sort,
            page: this.#page,
            price: this.#price,
            ...changes,
        })
    }

    /** Sorted and deduplicated: one state must have one URL, or a cache holds it twice. */
    static #tidy(values: readonly string[]) {
        return [...new Set(values)].sort()
    }

    toDescription(): StateDescription {
        return {
            facets: this.#facets,
            query: this.#query,
            sort: this.#sort,
            page: this.#page,
            price: { min: this.#price.min, max: this.#price.max },
        }
    }

    static valuesTo(values: readonly string[]) {
        return values.join(VALUE_SEPARATOR)
    }
}
