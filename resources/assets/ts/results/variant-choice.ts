import { CardVariant } from './card-variant.ts'
import { Range } from '../shared/range.ts'

import type { Card, Selection } from '../shared/description.ts'

export const ID_FIELD = 'id'
const VARIANTS_FIELD = 'variants'
const SEVERAL_FIELD = 'several_variants'
const OUT_OF_STOCK_FIELD = 'out_of_stock'
const FLAGS_OF_THE_CHOSEN_VARIANT: readonly string[] = [SEVERAL_FIELD, OUT_OF_STOCK_FIELD]
const FIELDS_SET_BY_THE_MODULE: readonly string[] = [ID_FIELD, VARIANTS_FIELD, ...FLAGS_OF_THE_CHOSEN_VARIANT]

/** The browser's copy of `Listing\VariantChoice`: shows a card through the variant the active filters point to, and as projected when none concerns its variants. */
export class VariantChoice {
    #selected: Selection
    #price: Range

    constructor(selected: Selection = {}, price = new Range()) {
        this.#selected = selected
        this.#price = price
    }

    shown(card: Card): Card {
        const { [VARIANTS_FIELD]: stored, ...rest } = card
        const variants = VariantChoice.#read(stored)
        const matching = this.#isConcerned(variants) ? this.#matching(variants) : []

        if (matching.length === 0) {
            return rest
        }

        const offered = VariantChoice.#offered(matching)
        const chosen = VariantChoice.#cheapest(offered)

        return {
            ...VariantChoice.#unflagged(rest),
            ...VariantChoice.#overrides(chosen),
            ...(offered.length > 1 ? { [SEVERAL_FIELD]: true } : {}),
            ...(chosen.inStock ? {} : { [OUT_OF_STOCK_FIELD]: true }),
        }
    }

    static #unflagged(card: Card): Card {
        const kept = Object.entries(card).filter(([field]) => !FLAGS_OF_THE_CHOSEN_VARIANT.includes(field))

        return Object.fromEntries(kept)
    }

    static #read(stored: unknown) {
        return VariantChoice.#listed(stored).map((variant) => CardVariant.read(variant)).filter((variant) => variant !== null)
    }

    /** JavaScript lists integer keys in ascending order whatever order the JSON wrote them in. */
    static #listed(stored: unknown): unknown[] {
        if (typeof stored !== 'object' || stored === null) {
            return []
        }

        const isIndexed = Object.keys(stored).every((key, index) => key === String(index))

        return isIndexed ? Object.values(stored) : []
    }

    static #overrides(variant: CardVariant) {
        return Object.fromEntries(Object.entries(variant.fields).filter(([field]) => !FIELDS_SET_BY_THE_MODULE.includes(field)))
    }

    #isConcerned(variants: CardVariant[]) {
        return !this.#price.isEmpty() || variants.some((variant) => variant.carriesAny(this.#selected))
    }

    #matching(variants: CardVariant[]) {
        return variants.filter((variant) => variant.matches(this.#selected, this.#price))
    }

    static #offered(matching: CardVariant[]) {
        const inStock = matching.filter((variant) => variant.inStock)

        return inStock.length === 0 ? matching : inStock
    }

    /** The first listed wins a tie. */
    static #cheapest(variants: CardVariant[]) {
        return variants.reduce((cheapest, variant) => variant.price < cheapest.price ? variant : cheapest)
    }
}
