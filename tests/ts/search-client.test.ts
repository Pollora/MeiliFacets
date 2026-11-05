import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { SearchClient, SearchError, SearchSuperseded } from '../../resources/assets/ts/shared/search-client.ts'

import type { TestContext } from 'node:test'
import type { SearchQuery } from '../../resources/assets/ts/shared/search-client.ts'

const CONNECTION = { url: 'https://engine.test/', key: 'search-only', index: 'posts' }
const QUERY: SearchQuery = { q: '', filter: '', hitsPerPage: 16, page: 1 }
const PLAN = { results: QUERY, brands: QUERY, sizes: QUERY }

const answering = (t: TestContext, respond: () => Promise<Response>) => t.mock.method(globalThis, 'fetch', respond)

const json = (body: unknown, status = 200) => () => Promise.resolve(new Response(JSON.stringify(body), { status }))

const failure = async (search: Promise<unknown>) => {
    try {
        await search
    } catch (error) {
        return error
    }

    return assert.fail('The search did not fail.')
}

describe('SearchClient', () => {
    it('hands each answer back under the key its search was sent with', async (t) => {
        answering(t, json({ results: [{ totalHits: 3 }, { totalHits: 2 }, { totalHits: 1 }] }))

        assert.deepEqual(await new SearchClient(CONNECTION).search(PLAN), {
            results: { totalHits: 3 },
            brands: { totalHits: 2 },
            sizes: { totalHits: 1 },
        })
    })

    it('fails like the server when the engine answers fewer searches than it was sent', async (t) => {
        answering(t, json({ results: [{ totalHits: 3 }, { totalHits: 2 }] }))

        const error = await failure(new SearchClient(CONNECTION).search(PLAN))

        assert.ok(error instanceof SearchError)
        assert.equal(error.message, 'Meilisearch answered 2 of 3 searches sent together.')
    })

    it('reports an engine it cannot reach as unavailable', async (t) => {
        answering(t, () => Promise.reject(new TypeError('Failed to fetch')))

        const error = await failure(new SearchClient(CONNECTION).search(PLAN))

        assert.ok(error instanceof SearchError)
        assert.equal(error.message, 'Failed to fetch')
    })

    it('reports an answer it cannot read as unavailable', async (t) => {
        answering(t, () => Promise.resolve(new Response('<html>Bad gateway</html>', { status: 200 })))

        assert.ok(await failure(new SearchClient(CONNECTION).search(PLAN)) instanceof SearchError)
    })

    it('keeps what the engine said when it refused', async (t) => {
        answering(t, json({ message: 'Invalid filter.', code: 'invalid_search_filter', type: 'invalid_request' }, 400))

        const error = await failure(new SearchClient(CONNECTION).search(PLAN))

        assert.ok(error instanceof SearchError)
        assert.deepEqual([error.message, error.status, error.code], ['Invalid filter.', 400, 'invalid_search_filter'])
    })

    it('ends a search it abandoned as overtaken, not as a failure', async (t) => {
        answering(t, () => Promise.reject(new DOMException('The operation was aborted.', 'AbortError')))

        assert.ok(await failure(new SearchClient(CONNECTION).search(PLAN)) instanceof SearchSuperseded)
    })
})
