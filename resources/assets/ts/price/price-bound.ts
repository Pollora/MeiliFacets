/** Keyed by bound, never by position: a missing input would shift the other one's meaning. */
export const PRICE_BOUNDS = ['min', 'max'] as const

/** The browser's copy of `Enums\PriceBound`. */
export type PriceBound = typeof PRICE_BOUNDS[number]
