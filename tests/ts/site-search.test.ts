import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { ANNOUNCE_DELAY_MS } from '../../resources/assets/ts/site-search/debounced-announcer.ts'
import { SEARCH_SHOWN, SiteSearch } from '../../resources/assets/ts/site-search/site-search.ts'
import { find, nextTurn, press } from './dom.ts'
import { FakeEngine, hit, openSearch, rearrangedSearchMarkup, searchDescribed, searchMarkup, settle, typeInto } from './site-search-fixtures.ts'

import type { TestContext } from 'node:test'

const CONNECTION = { url: 'https://engine.test', key: 'search-only', index: 'posts' }

const started = (t: TestContext, markup = searchMarkup()) => {
    t.mock.timers.enable({ apis: ['setTimeout'] })

    const engine = new FakeEngine(t)
    const errors = t.mock.method(console, 'error', () => undefined)
    const { window, root } = openSearch(markup)
    const element = (selector: string) => find(root, selector)
    const input = find<HTMLInputElement>(root, Contract.selector('search-input'))

    new SiteSearch({ contract: new Contract(root), description: searchDescribed(), connection: CONNECTION }).start()

    return {
        window,
        root,
        engine,
        errors,
        element,
        input,
        titles: (postType: string) => [...element(`[data-type="${postType}"]`).querySelectorAll(Contract.selector('title'))].map((title) => title.textContent),
        saidOnceSettled: () => {
            t.mock.timers.tick(ANNOUNCE_DELAY_MS)

            return element(Contract.selector('search-status')).textContent
        },
        hiddenSections: () => [...root.querySelectorAll<HTMLElement>(`${Contract.selector('search-section')}:not([data-leaving])`)].map((section) => section.hidden),
        typed: (term: string) => {
            typeInto(window, input, term)
            t.mock.timers.tick(120)
        },
    }
}

describe('SiteSearch', () => {
    it('sends the sections placed in one request, and paints each answer in its section', async (t) => {
        const { engine, typed, titles, saidOnceSettled, hiddenSections } = started(t)

        typed('ser')
        assert.deepEqual(engine.requests.map(({ queries }) => queries.map(({ q, hitsPerPage }) => [q, hitsPerPage])), [[['ser', 4], ['ser', 4]]])

        engine.last.answer([{ hits: [hit(1, 'Sérum')], totalHits: 4 }, { hits: [], totalHits: 0 }])
        await settle()

        assert.deepEqual(titles('product'), ['Sérum'])
        assert.deepEqual(hiddenSections(), [false, true])
        assert.equal(saidOnceSettled(), 'Products: 4 results')
    })

    it('announces once typing has paused, not at every answer', async (t) => {
        const { engine, typed, element, saidOnceSettled } = started(t)
        const region = () => element(Contract.selector('search-status')).textContent

        typed('se')
        engine.last.answer([{ hits: [hit(1, 'Sérum')], totalHits: 1 }, { hits: [], totalHits: 0 }])
        await settle()
        t.mock.timers.tick(ANNOUNCE_DELAY_MS - 200)
        typed('ser')
        t.mock.timers.tick(ANNOUNCE_DELAY_MS - 200)
        assert.equal(region(), '')

        engine.last.answer([{ hits: [hit(1, 'Sérum')], totalHits: 2 }, { hits: [], totalHits: 0 }])
        await settle()
        assert.equal(region(), '')
        assert.equal(saidOnceSettled(), 'Products: 2 results')
    })

    it('writes nothing for an answer the panel closed on, and says it again once reopened', async (t) => {
        const { root, engine, typed, element, saidOnceSettled } = started(t)
        const region = () => element(Contract.selector('search-status')).textContent
        const answer = async () => {
            engine.last.answer([{ hits: [hit(1, 'Sérum')], totalHits: 1 }, { hits: [], totalHits: 0 }])
            await settle()
        }

        root.setAttribute('data-open', '')
        typed('se')
        await answer()
        root.removeAttribute('data-open')
        await nextTurn()
        t.mock.timers.tick(ANNOUNCE_DELAY_MS)
        const whileClosed = region()

        root.setAttribute('data-open', '')
        typed('ser')
        await answer()

        assert.deepEqual([whileClosed, saidOnceSettled()], ['', 'Products: 1 result'])
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
        const { engine, typed, titles, saidOnceSettled, hiddenSections } = started(t)

        typed('ser')
        typed('s')
        engine.last.answer([{ hits: [hit(1, 'Late')], totalHits: 1 }, { hits: [], totalHits: 0 }])
        await settle()

        assert.equal(engine.last.signal.aborted, true)
        assert.deepEqual(titles('product'), [])
        assert.deepEqual(hiddenSections(), [true, true])
        assert.equal(saidOnceSettled(), '')
    })

    it('says nothing matched when every section comes back empty', async (t) => {
        const { engine, typed, element, saidOnceSettled, hiddenSections } = started(t)

        typed('zzzz')
        engine.last.answer([{ hits: [], totalHits: 0 }, { hits: [], totalHits: 0 }])
        await settle()

        assert.deepEqual(hiddenSections(), [true, true])
        assert.equal(element(Contract.selector('search-empty')).hidden, false)
        assert.equal(saidOnceSettled(), 'Nothing matches your search')
    })

    for (const [failure, fail] of [
        ['a refusal', (engine: FakeEngine) => engine.last.refuse(400, 'Attribute `url` is not searchable.')],
        ['no connection', (engine: FakeEngine) => engine.last.drop()],
    ] as const) {
        it(`shows the unavailable message alone after ${failure}`, async (t) => {
            const { engine, typed, element, saidOnceSettled, hiddenSections, errors } = started(t)

            typed('ser')
            engine.last.answer([{ hits: [hit(1, 'Sérum')], totalHits: 1 }, { hits: [hit(2, 'Guide')], totalHits: 1 }])
            await settle()
            typed('seru')
            fail(engine)
            await settle()

            assert.deepEqual(hiddenSections(), [true, true])
            assert.equal(element(Contract.selector('search-empty')).hidden, true)
            assert.equal(element(Contract.selector('search-unavailable')).hidden, false)
            assert.equal(saidOnceSettled(), 'Search is unavailable')
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

    it('names the listbox of every section it searches as what the field controls', (t) => {
        const { input } = started(t)

        assert.equal(input.getAttribute('aria-controls'), 'results-product results-post')
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

    describe('in a composition the theme reordered', () => {
        const product = {
            ID: 7,
            card: { title: 'Sérum', url: 'https://example.test/7', price: '<bdi>32 €</bdi>', image_url: 'https://example.test/7.webp' },
            _formatted: { card: { title: 'Sérum' } },
        }

        it('searches the sections in the order and with the limits the theme placed', (t) => {
            const { engine, typed } = started(t, rearrangedSearchMarkup())

            typed('ser')

            assert.deepEqual(engine.last.queries.map(({ filter, hitsPerPage }) => [filter, hitsPerPage]), [
                ['post_type = "post" AND post_status = "publish"', 2],
                ['post_type = "product" AND post_status = "publish"', 3],
            ])
        })

        it('fills a card whose hooks the theme moved, keeps them where the theme put them, and its nested link out of the Tab order', async (t) => {
            const { engine, typed, titles, element } = started(t, rearrangedSearchMarkup())

            typed('ser')
            engine.last.answer([{ hits: [hit(1, 'Guide')], totalHits: 1 }, { hits: [product], totalHits: 1 }])
            await settle()

            const card = element('[data-type="product"] [role="option"]')

            assert.deepEqual([titles('post'), titles('product')], [['Guide'], ['Sérum']])
            assert.equal(find(card, Contract.selector('price')).innerHTML, '<bdi>32 €</bdi>')
            assert.equal(find<HTMLImageElement>(card, Contract.selector('image')).src, 'https://example.test/7.webp')
            assert.equal(find<HTMLAnchorElement>(card, Contract.selector('url')).href, 'https://example.test/7')
            assert.equal(find(card, Contract.selector('url')).getAttribute('tabindex'), '-1')
            assert.deepEqual([...card.children].map((child) => child.getAttribute('data-meili') ?? child.tagName), ['price', 'DIV', 'summary', 'image'])
        })

        it('follows the order shown from the keyboard, and shows the message placed first', async (t) => {
            const { window, engine, typed, input, element } = started(t, rearrangedSearchMarkup())

            typed('ser')
            engine.last.answer([{ hits: [hit(1, 'Guide')], totalHits: 1 }, { hits: [product], totalHits: 1 }])
            await settle()
            press(window, input, 'ArrowDown')

            assert.equal(input.getAttribute('aria-activedescendant'), element('[data-type="post"] [role="option"]').id)

            typed('zzzz')
            engine.last.answer([{ hits: [], totalHits: 0 }, { hits: [], totalHits: 0 }])
            await settle()

            assert.equal(element(Contract.selector('search-empty')).hidden, false)
        })
    })
})
