import { RESULTS } from '../shared/plan.ts'
import { FIRST_PAGE } from './listing-state.ts'

import type { ListingDescription } from '../shared/description.ts'
import type { FilterQuery } from '../shared/filter-query.ts'
import type { Plan } from '../shared/plan.ts'
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
        const queries: Plan = { [RESULTS]: this.#results(state) }

        for (const filterQuery of this.#filterQueries) {
            if (filterQuery.isMeasuredApart(state)) {
                queries[filterQuery.key] = this.#apart(filterQuery, state)
            }
        }

        return queries
    }

    #results(state: ListingState): SearchQuery {
        const { perPage, attributes, sorts } = this.#listing
        const sort = state.sort !== null && Object.hasOwn(sorts, state.sort) ? sorts[state.sort] : undefined

        return {
            q: state.query,
            filter: this.#filterExpression(state, null),
            facets: this.#filterQueries.filter((query) => !query.isMeasuredApart(state)).flatMap((query) => query.fields),
            hitsPerPage: perPage,
            page: state.page,
            ...(attributes ? { attributesToRetrieve: attributes } : {}),
            ...(sort ? { sort } : {}),
        }
    }

    #apart(lifted: FilterQuery, state: ListingState): SearchQuery {
        return {
            q: state.query,
            filter: this.#filterExpression(state, lifted),
            facets: lifted.fields,
            hitsPerPage: NO_HIT,
            page: FIRST_PAGE,
        }
    }

    #filterExpression(state: ListingState, lifted: FilterQuery | null) {
        const clauses = this.#filterQueries
            .filter((query) => query.key !== lifted?.key)
            .map((query) => query.clause(state))

        return [this.#listing.filter, ...clauses].filter((clause) => clause !== '').join(' AND ')
    }
}
