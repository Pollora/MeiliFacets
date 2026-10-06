import type { Selection, SortFilterDescription } from '../shared/description.ts'
import type { Range } from '../shared/range.ts'

type Facets = Readonly<Record<string, readonly string[]>>

interface Parts {
    facets: Facets
    price: number
    fields: Readonly<Record<string, unknown>>
    inStock: boolean
    onSale: boolean
}

const FACETS_FIELD = 'facets'
const PRICE_FIELD = 'price'
const FIELDS_FIELD = 'fields'
const IN_STOCK_FIELD = 'in_stock'
const ON_SALE_FIELD = 'on_sale'
/** The document path `PriceField::OnSale` names on the server. */
const ON_SALE_PATH = 'price.onsale'

/** The browser's copy of `Listing\CardVariant`: one way a product is sold, inside its card. */
export class CardVariant {
    readonly facets: Facets
    readonly price: number
    readonly fields: Readonly<Record<string, unknown>>
    readonly inStock: boolean
    readonly onSale: boolean

    constructor({ facets, price, fields, inStock, onSale }: Parts) {
        this.facets = facets
        this.price = price
        this.fields = fields
        this.inStock = inStock
        this.onSale = onSale
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
            inStock: stored[IN_STOCK_FIELD] !== false,
            onSale: stored[ON_SALE_FIELD] === true,
        })
    }

    /** Of the fields a sort filters on, a variant holds its own on-sale flag; the others are the product's. */
    meetsSortFilter(filter: SortFilterDescription | null) {
        return filter === null || filter.field !== ON_SALE_PATH || this.onSale
    }

    /** A facet the variant does not carry does not rule it out. */
    matches(selected: Selection, price: Range) {
        return Object.entries(selected).every(([taxonomy, slugs]) => this.#meets(taxonomy, slugs))
            && price.contains(this.price)
    }

    #meets(taxonomy: string, selected: readonly string[]) {
        const carried = Object.hasOwn(this.facets, taxonomy) ? this.facets[taxonomy] : undefined

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
