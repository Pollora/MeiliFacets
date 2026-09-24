import { Money } from '../price/money.ts'

import type { FacetDescription, ListingDescription } from '../shared/description.ts'
import type { Range } from '../shared/range.ts'
import type { ListingState } from './listing-state.ts'

const PLACEHOLDER = /:([A-Za-z]+)/g

export interface ActiveValue {
    label: string
    name: string
    value: string
    action: string
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
        const name = this.#description.params[facet.taxonomy] ?? ''

        return state.selected(facet.taxonomy)
            .filter((slug) => Object.hasOwn(facet.labels, slug))
            .map((slug) => this.#removable(facet.labels[slug] ?? slug, name, slug))
    }

    #priced(price: Range) {
        return price.isEmpty() ? [] : [this.#removable(this.#priceLabel(price), this.#description.reserved.minPrice, '')]
    }

    #priceLabel({ min, max }: Range) {
        const patterns = this.#description.activeValuePatterns
        const written = { min: min === null ? '' : this.#money.of(min), max: max === null ? '' : this.#money.of(max) }

        if (max === null) {
            return this.#filled(patterns.from, written)
        }

        return this.#filled(min === null ? patterns.upTo : patterns.between, written)
    }

    #removable(label: string, name: string, value: string): ActiveValue {
        return { label, name, value, action: this.#filled(this.#description.activeValuePatterns.remove, { label }) }
    }

    /** One pass, like `strtr()`: a label holding `:max` or `$&` is written as it is. */
    #filled(pattern: string, values: Record<string, string>) {
        return pattern.replace(PLACEHOLDER, (placeholder, key: string) => (Object.hasOwn(values, key) ? values[key] : null) ?? placeholder)
    }
}
