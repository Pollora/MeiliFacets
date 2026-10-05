import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { FacetCounts } from '../../resources/assets/ts/facets/facet-counts.ts'
import { MEASURES, Measures, RESULTS } from '../../resources/assets/ts/shared/plan.ts'

const brand = { taxonomy: 'product_brand', multiple: true, cap: 30, visible: 10, labels: {}, counts: {} }
const answers = {
    [RESULTS]: { totalHits: 2, facetDistribution: { 'facets.product_brand': { acme: 1 } } },
    [MEASURES]: { facetDistribution: { 'facets.product_brand': { acme: 4 } } },
}

describe('Measures', () => {
    it('counts on the measures once the results read variants', () => {
        assert.deepEqual(new FacetCounts(answers).of(brand), { acme: 4 })
    })

    it('falls back on the results while they read products', () => {
        assert.deepEqual(Measures.in({ [RESULTS]: answers[RESULTS] }), answers[RESULTS])
    })
})
