import { facetField } from './description.js'
import { ListingQuery } from './listing-query.js'

/**
 * @import { FacetDescription } from './description.js'
 */

/**
 * Counts read off whichever response holds them: a facet that constrains the
 * results is counted by a search of its own, the others by the main one.
 */
export class FacetCounts {
    /** @type {Record<string, any>} */
    #answers

    /**
     * @param {Record<string, any>} answers
     */
    constructor(answers) {
        this.#answers = answers
    }

    /**
     * @param {FacetDescription} facet
     * @returns {Record<string, number>}
     */
    of(facet) {
        const apart = this.#answers[ListingQuery.countKey(facet.taxonomy)]
        const response = apart ?? this.#answers[ListingQuery.RESULTS] ?? {}

        return response.facetDistribution?.[facetField(facet)] ?? {}
    }
}

/**
 * Plural forms as the server translated them. Two forms only: a language that
 * needs three would need the rule as well, not just the strings.
 *
 * @param {string} pattern
 * @param {number} count
 */
export const countLabel = (pattern, count) => {
    const forms = pattern.split('|')

    return (forms[count === 1 ? 0 : 1] ?? forms[0] ?? '').replaceAll(':count', String(count))
}
