import { CardVariant } from './card-variant.ts'
import { Range } from '../shared/range.ts'

import type { Card } from '../shared/description.ts'

const VARIANTS_FIELD = 'variants'
const SEVERAL_FIELD = 'several_variants'

/** The browser's copy of `Listing\VariantChoice`: shows a card through the variant the active filters point to, and as projected when none concerns its variants. */
export class VariantChoice {
    #selected: Readonly<Record<string, readonly string[]>>
    #price: Range

    constructor(selected: Readonly<Record<string, readonly string[]>> = {}, price = new Range()) {
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

        return { ...rest, ...VariantChoice.#cheapest(matching).fields, ...(matching.length > 1 ? { [SEVERAL_FIELD]: true } : {}) }
    }

    static #read(stored: unknown) {
        if (typeof stored !== 'object' || stored === null) {
            return []
        }

        return Object.values(stored).map((variant) => CardVariant.read(variant)).filter((variant) => variant !== null)
    }

    #isConcerned(variants: CardVariant[]) {
        return !this.#price.isEmpty() || variants.some((variant) => variant.carriesAny(this.#selected))
    }

    #matching(variants: CardVariant[]) {
        return variants.filter((variant) => variant.matches(this.#selected, this.#price))
    }

    /** The first listed wins a tie. */
    static #cheapest(variants: CardVariant[]) {
        return variants.reduce((cheapest, variant) => variant.price < cheapest.price ? variant : cheapest)
    }
}
