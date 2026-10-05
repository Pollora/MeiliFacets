import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingQuery } from '../../resources/assets/ts/listing/listing-query.ts'
import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { FacetQuery } from '../../resources/assets/ts/facets/facet-query.ts'
import { PriceQuery } from '../../resources/assets/ts/price/price-query.ts'
import { FilterExpression } from '../../resources/assets/ts/shared/filter-expression.ts'
import { MEASURES, RESULTS } from '../../resources/assets/ts/shared/plan.ts'
import { described } from './fixtures.ts'

import type { StateChanges } from '../../resources/assets/ts/listing/listing-state.ts'
import type { ListingDescription } from '../../resources/assets/ts/shared/description.ts'

const listing = {
    perPage: 16,
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 30, visible: 10, labels: {}, counts: {} },
        { taxonomy: 'pa_size', multiple: true, cap: 30, visible: 10, labels: {}, counts: {} },
    ],
    filter: 'post_type = product',
    sorts: { price_asc: ['metas._price:asc'] },
}

const plan = (state: StateChanges, overrides: Partial<ListingDescription> = {}) => new ListingQuery(described({ ...listing, ...overrides }), filterQueriesOf(described({ ...listing, ...overrides }))).plan(new ListingState(state))
const build = (state: StateChanges, overrides: Partial<ListingDescription> = {}) => plan(state, overrides)[RESULTS]

describe('ListingQuery', () => {
    it('asks for the whole listing when nothing is selected', () => {
        const request = build({})

        assert.equal(request.filter, 'post_type = product')
        assert.equal(request.hitsPerPage, 16)
        assert.equal(request.page, 1)
        assert.deepEqual(request.facets, ['facets.product_brand', 'facets.pa_size'])
    })

    it('searches what the page itself searches for', () => {
        assert.equal(build({}, { baseQuery: 'creme' }).q, 'creme')
    })

    /** No field of the listing can take a term off a search WordPress routed. */
    it('ignores a typed term on a search WordPress routed', () => {
        assert.equal(build({ query: 'lait' }, { baseQuery: 'creme' }).q, 'creme')
    })

    it('joins the values of one facet with OR', () => {
        const { filter } = build({ facets: { product_brand: ['acme', 'globex'] } })

        assert.equal(
            filter,
            'post_type = product AND (facets.product_brand = "acme" OR facets.product_brand = "globex")'
        )
    })

    it('joins two facets with AND, and leaves a single value unwrapped', () => {
        const { filter } = build({
            facets: { product_brand: ['acme'], pa_size: ['large'] },
        })

        assert.equal(
            filter,
            'post_type = product AND facets.product_brand = "acme" AND facets.pa_size = "large"'
        )
    })

    it('ignores a facet left empty', () => {
        const { filter } = build({ facets: { product_brand: [] } })

        assert.equal(filter, 'post_type = product')
    })

    it('omits the base clause when the listing declares none', () => {
        const { filter } = build({ facets: { product_brand: ['acme'] } }, { filter: '' })

        assert.equal(filter, 'facets.product_brand = "acme"')
    })

    it('escapes a quote so it cannot close the value', () => {
        const { filter } = build({ facets: { product_brand: ['5" band'] } }, { filter: '' })

        assert.equal(filter, 'facets.product_brand = "5\\" band"')
    })

    // A value ending in a backslash used to close its own string, letting the
    // rest of the URL through as filter syntax.
    it('escapes a trailing backslash rather than letting it escape the closing quote', () => {
        const { filter } = build({ facets: { product_brand: ['back\\'] } }, { filter: '' })

        assert.equal(filter, 'facets.product_brand = "back\\\\"')
    })

    it('does not let an injected clause out of the value', () => {
        const { filter } = build(
            { facets: { product_brand: ['x" OR post_status = "draft'] } },
            { filter: '' }
        )

        assert.equal(filter, 'facets.product_brand = "x\\" OR post_status = \\"draft"')
    })

    it('asks for a page and clamps one below the first', () => {
        assert.equal(build({ page: 3 }).page, 3)
        assert.equal(build({ page: 0 }).page, 1)
        assert.equal(build({ page: -5 }).page, 1)
    })

    // The server keys its searches the same way: results, then count:<taxonomy>.
    it('plans one extra search per constrained multi-select facet', () => {
        assert.deepEqual(Object.keys(plan({})), ['results'])
        assert.deepEqual(
            Object.keys(plan({ facets: { product_brand: ['acme'] } })),
            ['results', 'count:product_brand']
        )
    })

    it('never counts a facet twice', () => {
        const state = { facets: { product_brand: ['acme'] } }
        const queries = plan(state)

        assert.ok(!queries.results.facets.includes('facets.product_brand'))
        assert.deepEqual(queries['count:product_brand']?.facets, ['facets.product_brand'])
    })

    it('lifts only the counted facet from its own filter', () => {
        const state = { facets: { product_brand: ['acme'], pa_size: ['large'] } }
        const counting = plan(state)['count:product_brand']

        assert.ok(counting)
        assert.ok(!counting.filter.includes('product_brand'))
        assert.ok(counting.filter.includes('facets.pa_size = "large"'))
        assert.equal(counting.hitsPerPage, 0)
    })

    it('leaves a single-select facet on the main search', () => {
        const single = { facets: [{ taxonomy: 'product_cat', multiple: false, cap: 1, visible: 10, labels: {}, counts: {} }] }
        const state = { facets: { product_cat: ['coats'] } }

        assert.deepEqual(Object.keys(plan(state, single)), ['results'])
        assert.deepEqual(plan(state, single).results.facets, ['facets.product_cat'])
    })

    it('sorts only by a sort the listing declares', () => {
        assert.deepEqual(build({ sort: 'price_asc' }).sort, ['metas._price:asc'])
        assert.equal(build({ sort: 'unknown' }).sort, undefined)
        assert.equal(build({ sort: 'constructor' }).sort, undefined)
        assert.equal(build({}).sort, undefined)
    })

    it('restricts the retrieved attributes when the listing names them', () => {
        assert.deepEqual(build({}, { attributes: ['card'] }).attributesToRetrieve, ['card'])

        const unnamed: Partial<ListingDescription> = described(listing)
        delete unnamed.attributes
        const query = new ListingQuery(unnamed as ListingDescription, filterQueriesOf(unnamed as ListingDescription))

        assert.equal(query.plan(new ListingState())[RESULTS].attributesToRetrieve, undefined)
    })
})

describe('ListingQuery with a sort that filters', () => {
    const promoted: Partial<ListingDescription> = {
        sorts: { price_asc: ['metas._price:asc'], on_sale: [] },
        priceFields: { min: 'price.min', max: 'price.max' },
        sortFilters: { on_sale: { field: 'price.onsale', value: 'true' } },
    }

    it('filters every search by it, as the server does', () => {
        const queries = plan({ facets: { product_brand: ['globex'] }, sort: 'on_sale', price: { min: 20 } }, promoted)

        for (const key of [RESULTS, FacetQuery.keyFor('product_brand'), PriceQuery.KEY]) {
            assert.match(queries[key]?.filter ?? '', / AND price\.onsale = "true"/)
        }

        assert.deepEqual(queries[RESULTS].sort, [])
    })

    it('asks the main search what it would keep', () => {
        assert.deepEqual(build({}, promoted).facets, ['facets.product_brand', 'facets.pa_size', 'price.min', 'price.max', 'price.onsale'])
    })

    it('filters nothing for a sort that only orders', () => {
        assert.doesNotMatch(build({ sort: 'price_asc' }, promoted).filter ?? '', /onsale/)
    })

    it('filters nothing for a sort key the prototype carries', () => {
        assert.doesNotMatch(build({ sort: 'constructor' }, promoted).filter ?? '', /undefined/)
    })
})

interface ScopeCase {
    case: string
    query: string
    baseQuery: string
    q: string
    filter: string
    attributesToSearchOn: string[] | null
}

interface SharedScopeCases {
    listing: { baseFilter: string[], searchScope: { filter: string[], fields: string[] } }
    cases: ScopeCase[]
}

/** The server reads the same cases (`QueryPlanTest`): render and first gesture search the same products. */
describe('ListingQuery, scope of a search shared with the server', () => {
    const shared = JSON.parse(readFileSync(new URL('../search-scope-cases.json', import.meta.url), 'utf8')) as SharedScopeCases
    const scoped = {
        facets: [{ taxonomy: 'product_brand', multiple: true, cap: 30, visible: 10, labels: {}, counts: {} }],
        filter: FilterExpression.all(shared.listing.baseFilter),
        searchScope: { filter: FilterExpression.all(shared.listing.searchScope.filter), fields: shared.listing.searchScope.fields },
    }

    for (const expected of shared.cases) {
        it(expected.case, () => {
            const queries = plan({ query: expected.query, facets: { product_brand: ['acme'] } }, { ...scoped, baseQuery: expected.baseQuery })
            const main = queries[RESULTS]
            const counting = queries['count:product_brand']

            assert.equal(main.q, expected.q)
            assert.equal(counting?.q, expected.q)
            assert.equal(main.filter, `${expected.filter} AND facets.product_brand = "acme"`)
            assert.equal(counting?.filter, expected.filter)
            assert.deepEqual(main.attributesToSearchOn ?? null, expected.attributesToSearchOn)
            assert.deepEqual(counting?.attributesToSearchOn ?? null, expected.attributesToSearchOn)
        })
    }
})

describe('ListingQuery reading variants', () => {
    const variantResults = {
        taxonomies: ['pa_size'],
        filter: 'post_type = product AND NOT document_kind = "parent"',
        searchScope: {
            filter: 'post_type = product AND NOT document_kind = "parent" AND searched',
            fields: ['post_title'],
        },
        distinct: 'parent_id',
        attributes: ['ID', 'card', 'parent_id'],
        sorts: { price_asc: ['in_stock:desc', 'metas._price:asc'] },
    }
    const priced = { variantResults, priceFields: { min: 'price.min', max: 'price.max' } }

    it('reads products while no filter concerns a variant', () => {
        const queries = plan({ facets: { product_brand: ['acme'] }, sort: 'price_asc' }, priced)

        assert.equal(queries[RESULTS].distinct, undefined)
        assert.equal(queries[MEASURES], undefined)
        assert.deepEqual(queries[RESULTS].sort, ['metas._price:asc'])
    })

    it('reads one variant per product once a size is ticked, in stock first under a price sort', () => {
        const results = plan({ facets: { pa_size: ['400ml'] }, sort: 'price_asc' }, priced)[RESULTS]

        assert.ok(results.filter.startsWith(variantResults.filter))
        assert.ok(results.filter.includes('facets.pa_size = "400ml"'))
        assert.equal(results.distinct, 'parent_id')
        assert.deepEqual(results.attributesToRetrieve, ['ID', 'card', 'parent_id'])
        assert.deepEqual(results.facets, [])
        assert.deepEqual(results.sort, ['in_stock:desc', 'metas._price:asc'])
    })

    it('reads variants through the search scope once a term is searched', () => {
        const results = plan({ facets: { pa_size: ['400ml'] }, query: 'lotion' }, priced)[RESULTS]

        assert.ok(results.filter.startsWith(variantResults.searchScope.filter))
        assert.deepEqual(results.attributesToSearchOn, ['post_title'])
    })

    it('measures on the products beside the variant results', () => {
        const measures = plan({ facets: { pa_size: ['400ml'] }, sort: 'price_asc' }, priced)[MEASURES]

        assert.ok(measures !== undefined)
        assert.ok(measures.filter.startsWith('post_type = product'))
        assert.ok(!measures.filter.includes('document_kind'))
        // The ticked size is counted by a search of its own.
        assert.deepEqual(measures.facets, ['facets.product_brand', 'price.min', 'price.max'])
        assert.equal(measures.hitsPerPage, 0)
        assert.equal(measures.sort, undefined)
    })

    it('reads variants under a price range alone', () => {
        assert.equal(plan({ price: { max: 30 } }, priced)[RESULTS].distinct, 'parent_id')
    })

    it('never reads variants of a listing without a document per variant', () => {
        assert.equal(plan({ price: { max: 30 } }, { priceFields: priced.priceFields })[RESULTS].distinct, undefined)
    })

    it('reads products off a page served before variants were described', () => {
        const older = { ...priced, variantResults: undefined } as unknown as Partial<ListingDescription>

        assert.equal(plan({ facets: { pa_size: ['400ml'] } }, older)[RESULTS].distinct, undefined)
    })
})
