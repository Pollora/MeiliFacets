import { ATTRIBUTE, Contract } from '../../resources/assets/ts/shared/contract.ts'

export const Hook = {
    activeValue: 'active-value',
    apply: 'apply',
    card: 'card',
    count: 'count',
    drawer: 'drawer',
    drawerOpen: 'drawer-open',
    drawerShowMore: 'drawer-show-more',
    empty: 'empty',
    facet: 'facet',
    facetValue: 'facet-value',
    input: 'input',
    listingSearchInput: 'listing-search-input',
    more: 'more',
    page: 'page',
    panel: 'panel',
    price: 'price',
    priceMax: 'price-max',
    reset: 'reset',
    searchInput: 'search-input',
    searchResults: 'search-results',
    searchSection: 'search-section',
    searchSeeAll: 'search-see-all',
    searchToggle: 'search-toggle',
    searchUnavailable: 'search-unavailable',
    sortChoice: 'sort-choice',
    total: 'total',
    url: 'url',
} as const

export type HookName = (typeof Hook)[keyof typeof Hook]

export class Hooks {
    static selector(hook: HookName): string {
        return Contract.selector(hook)
    }

    static nearestAncestor(hook: HookName): string {
        return `xpath=ancestor::*[@${ATTRIBUTE}="${hook}"][1]`
    }
}
