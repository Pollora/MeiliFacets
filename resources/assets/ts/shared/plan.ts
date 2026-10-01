import type { SearchQuery } from './search-client.ts'

export const RESULTS = 'results'

export type FacetedQuery = SearchQuery & { facets: string[] }

export type Plan = Record<typeof RESULTS, FacetedQuery> & Record<string, FacetedQuery>
