import { CardVariant } from './card-variant.ts'
import { Range } from '../shared/range.ts'

import type { Card, Selection, SortFilterDescription } from '../shared/description.ts'

export const ID_FIELD = 'id'
const VARIANTS_FIELD = 'variants'
const SEVERAL_FIELD = 'several_variants'
const OUT_OF_STOCK_FIELD = 'out_of_stock'
const FLAGS_OF_THE_CHOSEN_VARIANT: readonly string[] = [SEVERAL_FIELD, OUT_OF_STOCK_FIELD]
const FIELDS_SET_BY_THE_MODULE: readonly string[] = [ID_FIELD, VARIANTS_FIELD, ...FLAGS_OF_THE_CHOSEN_VARIANT]

/** What a listing knows when it shows its cards. */
export interface ChoiceContext {
    selected?: Selection
    price?: Range
    variantTaxonomies?: readonly string[]
    sortFilter?: SortFilterDescription | null | undefined
}

/** The browser's copy of `Listing\VariantChoice`. */
export class VariantChoice {
    #selected: Selection
    #price: Range
    #variantTaxonomies: readonly string[]
    #sortFilter: SortFilterDescription | null

    constructor({ selected = {}, price = new Range(), variantTaxonomies = [], sortFilter = null }: ChoiceContext = {}) {
        this.#selected = selected
        this.#price = price
        this.#variantTaxonomies = variantTaxonomies
        this.#sortFilter = sortFilter
    }

    shown(card: Card): Card {
        const { [VARIANTS_FIELD]: stored, ...rest } = card
        const matching = this.#readsVariants() ? this.#matching(VariantChoice.#read(stored)) : []

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

    #readsVariants() {
        return Object.keys(this.#selected).some((taxonomy) => this.#variantTaxonomies.includes(taxonomy))
    }

    #matching(variants: CardVariant[]) {
        return variants.filter((variant) => variant.matches(this.#selected, this.#price) && variant.meetsSortFilter(this.#sortFilter))
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
