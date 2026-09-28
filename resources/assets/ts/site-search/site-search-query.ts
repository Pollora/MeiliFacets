import { FIRST_PAGE } from '../listing/listing-state.ts'
import { FilterExpression } from '../shared/filter-expression.ts'
import { Highlight } from './highlight.ts'

import type { SearchTypeDescription } from '../shared/description.ts'
import type { SearchQuery } from '../shared/search-client.ts'

const RETRIEVED = ['ID', 'card']

// Naming `card.title` alone returns no `_formatted` at all: the whole card has to be asked for.
const HIGHLIGHTED = ['card']

export interface SearchedSection {
    type: SearchTypeDescription
    limit: number
}

/** One sub-query per section on the page, keyed by its post type: the answers come back under the same keys. */
export class SiteSearchQuery {
    #sections: readonly SearchedSection[]

    constructor(sections: readonly SearchedSection[]) {
        this.#sections = sections
    }

    plan(term: string): Record<string, SearchQuery> {
        return Object.fromEntries(this.#sections.map(({ type, limit }) => [type.postType, this.#query(term, type, limit)]))
    }

    #query(term: string, type: SearchTypeDescription, limit: number): SearchQuery {
        return {
            q: term,
            filter: FilterExpression.all(type.baseFilter),
            page: FIRST_PAGE,
            hitsPerPage: limit,
            attributesToSearchOn: type.searchOn,
            attributesToRetrieve: RETRIEVED,
            attributesToHighlight: HIGHLIGHTED,
            highlightPreTag: Highlight.OPENING,
            highlightPostTag: Highlight.CLOSING,
        }
    }
}
