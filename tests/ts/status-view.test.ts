import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { StatusView } from '../../resources/assets/ts/site-search/status-view.ts'
import { find } from './dom.ts'
import { openSearch, searchDescribed } from './site-search-fixtures.ts'

const status = (locale = 'en') => {
    const { root } = openSearch()
    const view = new StatusView(new Contract(root), searchDescribed({ locale }))
    const element = (hook: string) => find(root, Contract.selector(hook))

    return { view, element, said: () => element('search-status').textContent }
}

describe('StatusView', () => {
    it('names each section that found something, joined as the language joins a list', () => {
        const { view, said } = status()

        view.answered([{ heading: 'Products', total: 4 }, { heading: 'Posts', total: 0 }, { heading: 'Pages', total: 1 }])

        assert.equal(said(), 'Products: 4 results and Pages: 1 result')
    })

    it('joins in the language of the page', () => {
        const { view, said } = status('fr')

        view.answered([{ heading: 'Produits', total: 4 }, { heading: 'Articles', total: 2 }])

        assert.equal(said(), 'Produits: 4 results et Articles: 2 results')
    })

    it('reveals the empty message when every section is empty, and says it', () => {
        const { view, element, said } = status()

        view.answered([{ heading: 'Products', total: 0 }, { heading: 'Posts', total: 0 }])

        assert.equal(element('search-empty').hidden, false)
        assert.equal(element('search-unavailable').hidden, true)
        assert.equal(said(), 'Nothing matches your search')
    })

    it('reveals the unavailable message alone when the engine fails, and says it', () => {
        const { view, element, said } = status()

        view.answered([{ heading: 'Products', total: 0 }])
        view.failed()

        assert.equal(element('search-empty').hidden, true)
        assert.equal(element('search-unavailable').hidden, false)
        assert.equal(said(), 'Search is unavailable')
    })

    it('hides both messages and falls silent once cleared', () => {
        const { view, element, said } = status()

        view.failed()
        view.cleared()

        assert.equal(element('search-empty').hidden, true)
        assert.equal(element('search-unavailable').hidden, true)
        assert.equal(said(), '')
    })

    it('marks the panel busy while a search is out', () => {
        const { view, element } = status()

        view.searching()
        assert.equal(element('search-panel').getAttribute('aria-busy'), 'true')

        view.settled()
        assert.equal(element('search-panel').hasAttribute('aria-busy'), false)
    })
})
