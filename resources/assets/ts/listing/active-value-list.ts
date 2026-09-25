import { Money } from '../price/money.ts'

import type { FacetDescription, ListingDescription } from '../shared/description.ts'
import type { Range } from '../shared/range.ts'
import type { ListingState } from './listing-state.ts'

const PLACEHOLDER = /:([A-Za-z]+)/g

/** Mirrors `Enums\ActiveValueKind`: what a pill takes off, written on it as `data-kind`. */
export const KIND = 'data-kind'
export const TERM_KIND = 'term'
export const PRICE_KIND = 'price'

export type ActiveValueKind = typeof TERM_KIND | typeof PRICE_KIND

export interface ActiveValue {
    label: string
    parameter: string
    value: string
    action: string
    kind: ActiveValueKind
}

/** The browser's copy of `View\ActiveValueList`: the ticked values in facet order, then the price range. */
export class ActiveValueList {
    #description: ListingDescription
    #money: Money

    constructor(description: ListingDescription) {
        this.#description = description
        this.#money = new Money(description.money)
    }

    of(state: ListingState): ActiveValue[] {
        return [
            ...this.#description.facets.flatMap((facet) => this.#tickedIn(facet, state)),
            ...this.#priced(state.price),
        ]
    }

    /** A value the page rendered no label for gets no pill, as on the server. */
    #tickedIn(facet: FacetDescription, state: ListingState) {
        const parameter = this.#description.params[facet.taxonomy] ?? ''

        return state.selected(facet.taxonomy)
            .filter((slug) => Object.hasOwn(facet.labels, slug))
            .map((slug) => this.#removable(facet.labels[slug] ?? slug, TERM_KIND, { parameter, value: slug }))
    }

    #priced(price: Range) {
        const removed = { parameter: this.#description.reserved.minPrice, value: '' }

        return price.isEmpty() ? [] : [this.#removable(this.#priceLabel(price), PRICE_KIND, removed)]
    }

    #priceLabel({ min, max }: Range) {
        const patterns = this.#description.activeValuePatterns
        const written = { min: min === null ? '' : this.#money.of(min), max: max === null ? '' : this.#money.of(max) }

        if (max === null) {
            return this.#filled(patterns.from, written)
        }

        return this.#filled(min === null ? patterns.upTo : patterns.between, written)
    }

    #removable(label: string, kind: ActiveValueKind, { parameter, value }: Pick<ActiveValue, 'parameter' | 'value'>): ActiveValue {
        return { label, parameter, value, action: this.#filled(this.#description.activeValuePatterns.remove, { label }), kind }
    }

    /** One pass, like `strtr()`: a label holding `:max` or `$&` is written as it is. */
    #filled(pattern: string, values: Record<string, string>) {
        return pattern.replace(PLACEHOLDER, (placeholder, key: string) => (Object.hasOwn(values, key) ? values[key] : null) ?? placeholder)
    }
}
