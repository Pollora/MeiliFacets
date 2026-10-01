const LISTING_ATTRIBUTE = 'data-listing'
const SEARCH_ATTRIBUTE = 'data-search'
const SEARCH_PREFIX = 'search'

export interface Rule {
    host: string | null
    hooks: string[]
    whenHolding?: string
}

/**
 * A rule holds inside a host that is present. An absent host is markup the
 * theme chose not to render, or data that produced none — not a breach.
 */
const LISTING_RULES: Rule[] = [
    { host: null, hooks: ['results', 'card-template', 'empty'] },
    { host: 'facet-value', hooks: ['input'] },
    { host: 'facet', hooks: ['more'], whenHolding: 'facet-value' },
    { host: 'facet', hooks: ['panel'], whenHolding: 'toggle' },
    { host: 'pagination', hooks: ['page', 'previous', 'next'] },
    { host: 'sort', hooks: ['sort-trigger', 'sort-list', 'sort-option'] },
    { host: 'sort-choices', hooks: ['sort-choice'] },
    { host: 'sort-choices', hooks: ['panel'], whenHolding: 'toggle' },
    { host: 'price-range', hooks: ['price-track', 'price-handle'] },
    { host: 'listing-search', hooks: ['listing-search-input'] },
    { host: 'active-values', hooks: ['active-value-template'] },
    { host: 'active-value-template', hooks: ['active-value'] },
    { host: 'drawer', hooks: ['drawer-title', 'drawer-close'] },
]

const SEARCH_RULES: Rule[] = [
    { host: null, hooks: ['search-panel', 'search-input', 'search-status', 'search-empty', 'search-unavailable'] },
    { host: 'search-section', hooks: ['search-results', 'search-card-template', 'search-count'] },
    { host: 'search-card-template', hooks: ['url'] },
]

/** A root component a client binds to, named by its attribute, and the rules its markup must meet. */
export class RootComponent {
    static readonly LISTING = new RootComponent('listing', LISTING_ATTRIBUTE, LISTING_RULES)
    static readonly SEARCH = new RootComponent('search', SEARCH_ATTRIBUTE, SEARCH_RULES)
    static readonly ALL = [RootComponent.LISTING, RootComponent.SEARCH]

    readonly name: string
    readonly attribute: string
    readonly rules: readonly Rule[]

    private constructor(name: string, attribute: string, rules: Rule[]) {
        this.name = name
        this.attribute = attribute
        this.rules = rules
    }

    static get anySelector() {
        return RootComponent.ALL.map((component) => component.selector).join(', ')
    }

    static of(element: Element) {
        return RootComponent.ALL.find((component) => element.hasAttribute(component.attribute)) ?? null
    }

    static ownerOf(hook: string) {
        const isSearch = hook === SEARCH_PREFIX || hook.startsWith(`${SEARCH_PREFIX}-`)

        return isSearch ? RootComponent.SEARCH : RootComponent.LISTING
    }

    get selector() {
        return `[${this.attribute}]`
    }

    get tag() {
        return `<x-meilifacets::${this.name}>`
    }

    nameOf(element: Element) {
        return element.getAttribute(this.attribute) ?? ''
    }
}
