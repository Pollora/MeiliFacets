import type { Selection } from '../shared/description.ts'
import type { Range } from '../shared/range.ts'

type Facets = Readonly<Record<string, readonly string[]>>

interface Parts {
    facets: Facets
    price: number
    fields: Readonly<Record<string, unknown>>
}

const FACETS_FIELD = 'facets'
const PRICE_FIELD = 'price'
const FIELDS_FIELD = 'fields'

/** The browser's copy of `Listing\CardVariant`: one way a product is sold, inside its card. */
export class CardVariant {
    readonly facets: Facets
    readonly price: number
    readonly fields: Readonly<Record<string, unknown>>

    constructor({ facets, price, fields }: Parts) {
        this.facets = facets
        this.price = price
        this.fields = fields
    }

    /** Anything without a finite price is not a variant. */
    static read(stored: unknown) {
        if (!CardVariant.#isObject(stored)) {
            return null
        }

        const price = stored[PRICE_FIELD]

        if (typeof price !== 'number' || !Number.isFinite(price)) {
            return null
        }

        const fields = stored[FIELDS_FIELD]

        return new CardVariant({
            facets: CardVariant.#facetsOf(stored[FACETS_FIELD]),
            price,
            fields: CardVariant.#isObject(fields) ? fields : {},
        })
    }

    carriesAny(selected: Selection) {
        return Object.keys(selected).some((taxonomy) => Object.hasOwn(this.facets, taxonomy))
    }

    /** A facet the variant does not carry does not rule it out. */
    matches(selected: Selection, price: Range) {
        return Object.entries(selected).every(([taxonomy, slugs]) => this.#meets(taxonomy, slugs))
            && price.contains(this.price)
    }

    #meets(taxonomy: string, selected: readonly string[]) {
        const carried = this.facets[taxonomy]

        return carried === undefined || carried.some((slug) => selected.includes(slug))
    }

    static #facetsOf(stored: unknown): Facets {
        if (!CardVariant.#isObject(stored)) {
            return {}
        }

        return Object.fromEntries(Object.entries(stored).flatMap(([taxonomy, slugs]) => CardVariant.#isObject(slugs)
            ? [[taxonomy, Object.values(slugs).filter((slug): slug is string => typeof slug === 'string')]]
            : []))
    }

    static #isObject(value: unknown): value is Record<string, unknown> {
        return typeof value === 'object' && value !== null
    }
}
