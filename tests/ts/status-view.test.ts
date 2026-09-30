import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { ANNOUNCE_DELAY_MS } from '../../resources/assets/ts/site-search/debounced-announcer.ts'
import { StatusView } from '../../resources/assets/ts/site-search/status-view.ts'
import { find } from './dom.ts'
import { openSearch, searchDescribed } from './site-search-fixtures.ts'

import type { TestContext } from 'node:test'

const status = (t: TestContext, locale = 'en') => {
    t.mock.timers.enable({ apis: ['setTimeout'] })

    const { root } = openSearch()
    const view = new StatusView(new Contract(root), searchDescribed({ locale }))
    const element = (hook: string) => find(root, Contract.selector(hook))

    return {
        view,
        element,
        saidOnceSettled: () => {
            t.mock.timers.tick(ANNOUNCE_DELAY_MS)

            return element('search-status').textContent
        },
    }
}

describe('StatusView', () => {
    it('names each section that found something, joined as the language joins a list', (t) => {
        const { view, saidOnceSettled } = status(t)

        view.answered([{ heading: 'Products', total: 4 }, { heading: 'Posts', total: 0 }, { heading: 'Pages', total: 1 }])

        assert.equal(saidOnceSettled(), 'Products: 4 results and Pages: 1 result')
    })

    it('joins in the language of the page', (t) => {
        const { view, saidOnceSettled } = status(t, 'fr')

        view.answered([{ heading: 'Produits', total: 4 }, { heading: 'Articles', total: 2 }])

        assert.equal(saidOnceSettled(), 'Produits: 4 results et Articles: 2 results')
    })

    it('reveals the empty message when every section is empty, and says it', (t) => {
        const { view, element, saidOnceSettled } = status(t)

        view.answered([{ heading: 'Products', total: 0 }, { heading: 'Posts', total: 0 }])

        assert.equal(element('search-empty').hidden, false)
        assert.equal(element('search-unavailable').hidden, true)
        assert.equal(saidOnceSettled(), 'Nothing matches your search')
    })

    it('reveals the unavailable message alone when the engine fails, and says it', (t) => {
        const { view, element, saidOnceSettled } = status(t)

        view.answered([{ heading: 'Products', total: 0 }])
        view.failed()

        assert.equal(element('search-empty').hidden, true)
        assert.equal(element('search-unavailable').hidden, false)
        assert.equal(saidOnceSettled(), 'Search is unavailable')
    })

    it('hides both messages and falls silent once cleared', (t) => {
        const { view, element, saidOnceSettled } = status(t)

        view.failed()
        assert.equal(saidOnceSettled(), 'Search is unavailable')

        view.cleared()

        assert.equal(element('search-empty').hidden, true)
        assert.equal(element('search-unavailable').hidden, true)
        assert.equal(saidOnceSettled(), '')
    })

    it('forgets what it was about to say once the panel closes, and says it again after', (t) => {
        const { view, saidOnceSettled } = status(t)

        view.answered([{ heading: 'Products', total: 4 }])
        view.closed()
        const whileClosed = saidOnceSettled()

        view.answered([{ heading: 'Products', total: 4 }])

        assert.deepEqual([whileClosed, saidOnceSettled()], ['', 'Products: 4 results'])
    })

    it('marks the panel busy while a search is out', (t) => {
        const { view, element } = status(t)

        view.searching()
        assert.equal(element('search-panel').getAttribute('aria-busy'), 'true')

        view.settled()
        assert.equal(element('search-panel').hasAttribute('aria-busy'), false)
    })
})
