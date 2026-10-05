import { FilterExpression } from '../shared/filter-expression.ts'
import { MEASURES, RESULTS } from '../shared/plan.ts'
import { FIRST_PAGE } from './listing-state.ts'

import type { ListingDescription, SearchScope, VariantResultsDescription } from '../shared/description.ts'
import type { FilterQuery } from '../shared/filter-query.ts'
import type { FacetedQuery, Plan } from '../shared/plan.ts'
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
            ? { [RESULTS]: this.#variantResults(variants, state), [MEASURES]: this.#measures(state) }
            : { [RESULTS]: this.#results(state) }

        for (const filterQuery of this.#filterQueries) {
            if (filterQuery.isMeasuredSeparately(state)) {
                queries[filterQuery.key] = this.#measureWithout(filterQuery, state)
            }
        }

        return queries
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
        const filtersAVariant = Object.keys(state.facets).some((taxonomy) => variants.taxonomies.includes(taxonomy))

        return filtersAVariant || !state.price.isEmpty()
    }

    #variantResults(variants: VariantResultsDescription, state: ListingState): FacetedQuery {
        const sort = ListingQuery.#sortIn(variants.sorts, state)
        const scope = this.#searchTerm(state) === '' ? { filter: variants.filter, fields: null } : variants.searchScope

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

    #measures(state: ListingState): FacetedQuery {
        const scope = this.#scope(state)

        return {
            q: this.#searchTerm(state),
            filter: this.#filterExpression(scope, state, null),
            facets: this.#fieldsMeasuredTogether(state),
            hitsPerPage: NO_HIT,
            page: FIRST_PAGE,
            ...this.#searchedFields(scope),
        }
    }

    #fieldsMeasuredTogether(state: ListingState) {
        return this.#filterQueries
            .filter((query) => !query.isMeasuredSeparately(state))
            .flatMap((query) => query.fields)
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
        const scope = this.#scope(state)

        return {
            q: this.#searchTerm(state),
            filter: this.#filterExpression(scope, state, lifted),
            facets: lifted.fields,
            hitsPerPage: NO_HIT,
            page: FIRST_PAGE,
            ...this.#searchedFields(scope),
        }
    }

    #filterExpression(scope: SearchScope, state: ListingState, lifted: FilterQuery | null) {
        const clauses = this.#filterQueries
            .filter((query) => query.key !== lifted?.key)
            .map((query) => query.clause(state))

        return FilterExpression.all([scope.filter, ...clauses])
    }
}
