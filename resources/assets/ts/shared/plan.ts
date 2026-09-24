import type { SearchQuery } from './search-client.ts'

export const RESULTS = 'results'

export type Plan = Record<typeof RESULTS, SearchQuery> & Record<string, SearchQuery>
