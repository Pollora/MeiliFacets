import { facetField } from '../shared/description.ts'
import { RESULTS } from '../shared/plan.ts'
import { FacetQuery } from './facet-query.ts'

import type { FacetDescription } from '../shared/description.ts'
import type { Answers } from '../shared/search-client.ts'

/**
 * Counts read off whichever response holds them: a facet that constrains the
 * results is counted by a search of its own, the others by the main one.
 */
export class FacetCounts {
    #answers: Answers

    constructor(answers: Answers) {
        this.#answers = answers
    }

    of(facet: FacetDescription): Record<string, number> {
        const apart = this.#answers[FacetQuery.keyFor(facet.taxonomy)]
        const response = apart ?? this.#answers[RESULTS] ?? {}

        return response.facetDistribution?.[facetField(facet)] ?? {}
    }
}
