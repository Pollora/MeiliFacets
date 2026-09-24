/**
 * The shape below is the PHP/TypeScript contract, written once here and once in `ListingDescription`.
 */
export interface FacetDescription {
    taxonomy: string
    multiple: boolean
    /** how many values a URL may carry for this facet */
    cap: number
    /** how many values are read at once, the rest folded away */
    visible: number
    /** slug to count, for the values the page was served with */
    counts: Readonly<Record<string, number>>
    /** slug to label, for the values the page was served with, folded ones included */
    labels: Readonly<Record<string, string>>
}

/** `:label`, `:min` and `:max` are filled by the client */
export interface ActiveValuePatterns {
    remove: string
    between: string
    from: string
    upTo: string
}

export interface PriceFields {
    min: string
    max: string
}

export interface SortFilterDescription {
    field: string
    value: string
}

export interface MoneyFormat {
    format: string
    symbol: string
    decimals: number
    decimal: string
    thousand: string
}

export interface StateDescription {
    facets: Readonly<Record<string, readonly string[]>>
    query: string
    sort: string | null
    page: number
    price: { min: number | null, max: number | null }
}

export interface ListingDescription {
    name: string
    filter: string
    /** What the page itself searches for: a WordPress search served by the listing. */
    baseQuery: string
    perPage: number
    /** hits the engine will serve past which no page exists */
    reachableHits: number
    attributes: string[]
    /** key to engine sort expressions */
    sorts: Record<string, string[]>
    /** sort key to the filter it carries */
    sortFilters: Readonly<Record<string, SortFilterDescription>>
    /** taxonomy to URL parameter */
    params: Record<string, string>
    reserved: Record<'sort' | 'query' | 'page' | 'minPrice' | 'maxPrice', string>
    priceFields: PriceFields | null
    money: MoneyFormat | null
    /** singular and plural forms, separated by a pipe */
    countPattern: string
    filterPattern: string
    totalPattern: string
    activeValuePatterns: ActiveValuePatterns
    /** the language whose plural rule picks a form of the patterns */
    locale: string
    facets: FacetDescription[]
    apply: 'submit' | 'immediate'
    state: StateDescription
    /** the path of the page's first page */
    pagePath: string
    /** already encoded: written as is */
    pageQuery: string
}

export type Card = Partial<Record<string, unknown>>

export interface Connection {
    url: string
    key: string
    index: string
}

const FACET_FIELD_PREFIX = 'facets.'

export const facetField = (facet: FacetDescription) => FACET_FIELD_PREFIX + facet.taxonomy
