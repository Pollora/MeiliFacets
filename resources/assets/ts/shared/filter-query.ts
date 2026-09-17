import type { ListingState } from '../listing/listing-state.ts'

export interface FilterQuery {
    readonly key: string
    readonly fields: string[]
    clause(state: ListingState): string
    isMeasuredApart(state: ListingState): boolean
}
