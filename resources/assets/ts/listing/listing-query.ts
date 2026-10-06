import { FacetQuery } from '../facets/facet-query.ts'
import { PriceQuery } from '../price/price-query.ts'
import { FilterExpression } from '../shared/filter-expression.ts'
import { MEASURES, RESULTS } from '../shared/plan.ts'
import { FIRST_PAGE } from './listing-state.ts'

import type { ListingDescription, SearchScope, VariantResultsDescription } from '../shared/description.ts'
import type { FilterQuery } from '../shared/filter-query.ts'
import type { FacetedQuery, Plan } from '../shared/plan.ts'
import type { SearchQuery } from '../shared/search-client.ts'
import type { ListingState } from './listing-state.ts'

const NO_HIT = 0

/**
 * Mirrors QueryPlan on the server: the same state must produce the same searches
 * on both sides, or the grid contradicts itself between render and first click.
 */
export class ListingQuery {
    #listing: ListingDescription
    #filterQueries: FilterQuery[]

    constructor(description: ListingDescription, filterQueries: FilterQuery[]) {
        this.#listing = description
        this.#filterQueries = filterQueries
    }

    plan(state: ListingState) {
        const variants = this.#listing.variantResults ?? null
        const queries: Plan = variants !== null && this.#readsVariants(variants, state)
            ? { [RESULTS]: this.#variantResults(variants, state), [MEASURES]: this.#measures(variants, state) }
            : { [RESULTS]: this.#results(state) }

        for (const filterQuery of this.#filterQueries) {
            if (this.#needsSearchOfItsOwn(filterQuery, state)) {
                queries[filterQuery.key] = this.#measureWithout(filterQuery, state)
            }
        }

        return queries
    }

    #needsSearchOfItsOwn(query: FilterQuery, state: ListingState) {
        if (query.isMeasuredSeparately(state)) {
            return true
        }

        const variants = this.#listing.variantResults ?? null

        if (variants === null) {
            return false
        }

        const isVariantFacet = variants.taxonomies.some((taxonomy) => FacetQuery.keyFor(taxonomy) === query.key)
        const isBoundOnVariants = query.key === PriceQuery.KEY && this.#readsVariants(variants, state)

        return isVariantFacet || isBoundOnVariants
    }

    #results(state: ListingState): FacetedQuery {
        const { perPage, attributes, sorts } = this.#listing
        const sort = ListingQuery.#sortIn(sorts, state)
        const scope = this.#scope(state)

        return {
            q: this.#searchTerm(state),
            filter: this.#filterExpression(scope, state, null),
            facets: this.#fieldsMeasuredTogether(state),
            hitsPerPage: perPage,
            page: state.page,
            ...(attributes ? { attributesToRetrieve: attributes } : {}),
            ...(sort ? { sort } : {}),
            ...this.#searchedFields(scope),
        }
    }

    #readsVariants(variants: VariantResultsDescription, state: ListingState) {
        return Object.keys(state.facets).some((taxonomy) => variants.taxonomies.includes(taxonomy))
    }

    #variantResults(variants: VariantResultsDescription, state: ListingState): FacetedQuery {
        const sort = ListingQuery.#sortIn(variants.sorts, state)
        const scope = this.#variantScope(variants, state)

        return {
            q: this.#searchTerm(state),
            filter: this.#filterExpression(scope, state, null),
            facets: [],
            distinct: variants.distinct,
            hitsPerPage: this.#listing.perPage,
            page: state.page,
            attributesToRetrieve: variants.attributes,
            ...(sort ? { sort } : {}),
            ...this.#searchedFields(scope),
        }
    }

    #measures(variants: VariantResultsDescription, state: ListingState): FacetedQuery {
        return { ...this.#onVariantsOncePerProduct(variants, state, null), facets: this.#fieldsMeasuredTogether(state) }
    }

    #fieldsMeasuredTogether(state: ListingState) {
        return this.#filterQueries
            .filter((query) => !this.#needsSearchOfItsOwn(query, state))
            .flatMap((query) => query.fields)
    }

    #variantScope(variants: VariantResultsDescription, state: ListingState): SearchScope {
        return this.#searchTerm(state) === '' ? { filter: variants.filter, fields: null } : variants.searchScope
    }

    static #sortIn(sorts: Record<string, string[]>, state: ListingState) {
        return state.sort !== null && Object.hasOwn(sorts, state.sort) ? sorts[state.sort] : undefined
    }

    #searchTerm(state: ListingState) {
        const routed = this.#listing.baseQuery

        return routed !== '' ? routed : state.query
    }

    #scope(state: ListingState): SearchScope {
        return this.#searchTerm(state) === '' ? { filter: this.#listing.filter, fields: null } : this.#listing.searchScope
    }

    #searchedFields({ fields }: SearchScope) {
        return fields === null ? {} : { attributesToSearchOn: fields }
    }

    #measureWithout(lifted: FilterQuery, state: ListingState): FacetedQuery {
        return { ...this.#measuringWithout(lifted, state), facets: lifted.fields }
    }

    #measuringWithout(lifted: FilterQuery, state: ListingState): SearchQuery {
        const variants = this.#listing.variantResults ?? null

        if (variants === null) {
            return this.#onProducts(state, lifted)
        }

        if (ListingQuery.#differsBetweenVariants(variants, lifted)) {
            return this.#onVariants(variants, state, lifted)
        }

        if (this.#readsVariants(variants, state)) {
            return this.#onVariantsOncePerProduct(variants, state, lifted)
        }

        return this.#onProducts(state, lifted)
    }

    /** `distinct` only deduplicates the counts in page mode. */
    #onVariantsOncePerProduct(
        variants: VariantResultsDescription,
        state: ListingState,
        lifted: FilterQuery | null,
    ): SearchQuery {
        return { ...this.#onVariants(variants, state, lifted), distinct: variants.distinct }
    }

    #onVariants(variants: VariantResultsDescription, state: ListingState, lifted: FilterQuery | null): SearchQuery {
        return this.#measuring(this.#variantScope(variants, state), state, lifted)
    }

    #onProducts(state: ListingState, lifted: FilterQuery | null): SearchQuery {
        return this.#measuring(this.#scope(state), state, lifted)
    }

    #measuring(scope: SearchScope, state: ListingState, lifted: FilterQuery | null): SearchQuery {
        return {
            q: this.#searchTerm(state),
            filter: this.#filterExpression(scope, state, lifted),
            hitsPerPage: NO_HIT,
            page: FIRST_PAGE,
            ...this.#searchedFields(scope),
        }
    }

    static #differsBetweenVariants(variants: VariantResultsDescription, query: FilterQuery) {
        const variantKeys = [PriceQuery.KEY, ...variants.taxonomies.map((taxonomy) => FacetQuery.keyFor(taxonomy))]

        return variantKeys.includes(query.key)
    }

    #filterExpression(scope: SearchScope, state: ListingState, lifted: FilterQuery | null) {
        const clauses = this.#filterQueries
            .filter((query) => query.key !== lifted?.key)
            .map((query) => query.clause(state))

        return FilterExpression.all([scope.filter, ...clauses])
    }
}
