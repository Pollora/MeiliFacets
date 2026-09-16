import { ListingQuery } from './listing-query.js'

/**
 * @import { ListingDescription } from './description.js'
 * @import { Span } from './price-control.js'
 */

export class PriceBounds {
    /** @type {Record<string, any>} */
    #answers

    /**
     * @param {Record<string, any>} answers
     */
    constructor(answers) {
        this.#answers = answers
    }

    /**
     * @param {ListingDescription['priceFields'] | undefined} fields
     * @returns {Span | null}
     */
    of(fields) {
        if (!fields) {
            return null
        }

        const response = this.#answers[ListingQuery.BOUNDS] ?? this.#answers[ListingQuery.RESULTS] ?? {}
        const min = response.facetStats?.[fields.min]?.min
        const max = response.facetStats?.[fields.max]?.max

        return typeof min === 'number' && typeof max === 'number'
            ? { min: Math.floor(min), max: Math.ceil(max) }
            : null
    }
}
