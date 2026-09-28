import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { SEARCH_SHOWN, SiteSearch } from '../../resources/assets/ts/site-search/site-search.ts'
import { find } from './dom.ts'
import { FakeEngine, hit, openSearch, searchDescribed, settle, typeInto } from './site-search-fixtures.ts'

import type { TestContext } from 'node:test'

const CONNECTION = { url: 'https://engine.test', key: 'search-only', index: 'posts' }

const started = (t: TestContext) => {
    t.mock.timers.enable({ apis: ['setTimeout'] })

    const engine = new FakeEngine(t)
    const errors = t.mock.method(console, 'error', () => undefined)
    const { window, root } = openSearch()
    const element = (selector: string) => find(root, selector)
    const input = find<HTMLInputElement>(root, Contract.selector('search-input'))

    new SiteSearch({ contract: new Contract(root), description: searchDescribed(), connection: CONNECTION }).start()

    return {
        engine,
        errors,
        element,
        input,
        titles: (postType: string) => [...element(`[data-type="${postType}"]`).querySelectorAll(Contract.selector('title'))].map((title) => title.textContent),
        said: () => element(Contract.selector('search-status')).textContent,
        hiddenSections: () => [...root.querySelectorAll<HTMLElement>(Contract.selector('search-section'))].map((section) => section.hidden),
        typed: (term: string) => {
            typeInto(window, input, term)
            t.mock.timers.tick(120)
        },
    }
}

describe('SiteSearch', () => {
    it('sends the sections placed in one request, and paints each answer in its section', async (t) => {
        const { engine, typed, titles, said, hiddenSections } = started(t)

        typed('ser')
        assert.deepEqual(engine.requests.map(({ queries }) => queries.map(({ q, hitsPerPage }) => [q, hitsPerPage])), [[['ser', 4], ['ser', 4]]])

        engine.last.answer([{ hits: [hit(1, 'Sérum')], totalHits: 4 }, { hits: [], totalHits: 0 }])
        await settle()

        assert.deepEqual(titles('product'), ['Sérum'])
        assert.deepEqual(hiddenSections(), [false, true])
        assert.equal(said(), 'Products: 4 results')
    })

    it('paints only the last answer of a term typed while the first was out', async (t) => {
        const { engine, typed, titles } = started(t)

        typed('se')
        const first = engine.last
        typed('ser')
        first.answer([{ hits: [hit(1, 'Stale')], totalHits: 1 }, { hits: [], totalHits: 0 }])
        await settle()

        assert.equal(first.signal.aborted, true)
        assert.deepEqual(titles('product'), [])

        engine.last.answer([{ hits: [hit(2, 'Fresh')], totalHits: 1 }, { hits: [], totalHits: 0 }])
        await settle()

        assert.deepEqual(titles('product'), ['Fresh'])
    })

    it('abandons the search out, and paints nothing, once the term falls under the threshold', async (t) => {
        const { engine, typed, titles, said, hiddenSections } = started(t)

        typed('ser')
        typed('s')
        engine.last.answer([{ hits: [hit(1, 'Late')], totalHits: 1 }, { hits: [], totalHits: 0 }])
        await settle()

        assert.equal(engine.last.signal.aborted, true)
        assert.deepEqual(titles('product'), [])
        assert.deepEqual(hiddenSections(), [true, true])
        assert.equal(said(), '')
    })

    it('says nothing matched when every section comes back empty', async (t) => {
        const { engine, typed, element, said, hiddenSections } = started(t)

        typed('zzzz')
        engine.last.answer([{ hits: [], totalHits: 0 }, { hits: [], totalHits: 0 }])
        await settle()

        assert.deepEqual(hiddenSections(), [true, true])
        assert.equal(element(Contract.selector('search-empty')).hidden, false)
        assert.equal(said(), 'Nothing matches your search')
    })

    for (const [failure, fail] of [
        ['a refusal', (engine: FakeEngine) => engine.last.refuse(400, 'Attribute `url` is not searchable.')],
        ['no connection', (engine: FakeEngine) => engine.last.drop()],
    ] as const) {
        it(`shows the unavailable message alone after ${failure}`, async (t) => {
            const { engine, typed, element, said, hiddenSections, errors } = started(t)

            typed('ser')
            engine.last.answer([{ hits: [hit(1, 'Sérum')], totalHits: 1 }, { hits: [hit(2, 'Guide')], totalHits: 1 }])
            await settle()
            typed('seru')
            fail(engine)
            await settle()

            assert.deepEqual(hiddenSections(), [true, true])
            assert.equal(element(Contract.selector('search-empty')).hidden, true)
            assert.equal(element(Contract.selector('search-unavailable')).hidden, false)
            assert.equal(said(), 'Search is unavailable')
            assert.equal(errors.mock.callCount(), 1)
        })
    }

    it('keeps the panel busy from the first search out until none is', async (t) => {
        const { engine, typed, element } = started(t)
        const busy = () => element(Contract.selector('search-panel')).getAttribute('aria-busy')

        typed('se')
        typed('ser')
        assert.equal(busy(), 'true')

        await settle()
        assert.equal(busy(), 'true')

        engine.last.answer([{ hits: [], totalHits: 0 }, { hits: [], totalHits: 0 }])
        await settle()
        assert.equal(busy(), null)
    })

    it('offers the cards of every section to the keyboard, in the order they are shown', async (t) => {
        const { engine, typed, input } = started(t)

        typed('ser')
        engine.last.answer([{ hits: [hit(1, 'Sérum')], totalHits: 1 }, { hits: [hit(2, 'Guide')], totalHits: 1 }])
        await settle()

        assert.equal(input.getAttribute('aria-expanded'), 'true')
        assert.deepEqual([...input.ownerDocument.querySelectorAll('[role="option"][id]')].map((option) => option.id), [
            'search-input-option-0',
            'search-input-option-1',
        ])
    })

    it('measures the time from a search leaving to its answer shown', async (t) => {
        const { engine, typed } = started(t)

        performance.clearMeasures(SEARCH_SHOWN)
        typed('ser')
        engine.last.answer([{ hits: [], totalHits: 0 }, { hits: [], totalHits: 0 }])
        await settle()

        assert.equal(performance.getEntriesByName(SEARCH_SHOWN, 'measure').length, 1)
    })
})
