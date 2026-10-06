import type { Answers, SearchAnswer, SearchQuery } from './search-client.ts'

export const RESULTS = 'results'

export const MEASURES = 'measures'

export type FacetedQuery = SearchQuery & { facets: string[] }

export type Plan = Record<typeof RESULTS, FacetedQuery> & Record<string, FacetedQuery>

export class Measures {
    static in(answers: Answers): SearchAnswer {
        return answers[MEASURES] ?? answers[RESULTS] ?? {}
    }
}
