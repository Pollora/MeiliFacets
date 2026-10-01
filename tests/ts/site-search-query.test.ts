import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { FilterExpression } from '../../resources/assets/ts/shared/filter-expression.ts'
import { SiteSearchQuery } from '../../resources/assets/ts/site-search/site-search-query.ts'
import { CLOSING, OPENING, searchDescribed } from './site-search-fixtures.ts'

describe('SiteSearchQuery', () => {
    const [product, post] = searchDescribed().types

    it('asks one sub-query per section, keyed by its type, in the shape the engine accepts', () => {
        assert.ok(product && post)

        assert.deepEqual(new SiteSearchQuery([{ type: product, limit: 4 }, { type: post, limit: 2 }]).plan('serum'), {
            product: {
                q: 'serum',
                filter: 'post_type = "product" AND post_status = "publish"',
                page: 1,
                hitsPerPage: 4,
                attributesToSearchOn: ['post_title', 'metas._sku'],
                attributesToRetrieve: ['ID', 'card'],
                attributesToHighlight: ['card'],
                highlightPreTag: OPENING,
                highlightPostTag: CLOSING,
            },
            post: {
                q: 'serum',
                filter: 'post_type = "post" AND post_status = "publish"',
                page: 1,
                hitsPerPage: 2,
                attributesToSearchOn: ['post_title', 'excerpt'],
                attributesToRetrieve: ['ID', 'card'],
                attributesToHighlight: ['card'],
                highlightPreTag: OPENING,
                highlightPostTag: CLOSING,
            },
        })
    })

    it('asks nothing of a type no section was placed for', () => {
        assert.ok(post)

        assert.deepEqual(Object.keys(new SiteSearchQuery([{ type: post, limit: 4 }]).plan('serum')), ['post'])
    })
})

describe('FilterExpression.all', () => {
    it('joins the clauses that say something', () => {
        assert.equal(FilterExpression.all(['a = "1"', '', 'b = "2"']), 'a = "1" AND b = "2"')
        assert.equal(FilterExpression.all(['', '']), '')
    })
})
