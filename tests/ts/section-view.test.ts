import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { SectionView } from '../../resources/assets/ts/site-search/section-view.ts'
import { find } from './dom.ts'
import { hit, OPENING, CLOSING, openSearch, searchDescribed, searchMarkup, searchSection } from './site-search-fixtures.ts'

import type { TestContext } from 'node:test'

const sections = (t: TestContext, markup = searchMarkup()) => {
    const errors = t.mock.method(console, 'error', () => undefined)
    const { root } = openSearch(markup)
    const views = SectionView.allIn(new Contract(root), searchDescribed())

    return { root, views, errors: errors.mock.calls.map((call) => String(call.arguments[0])) }
}

const section = (root: HTMLElement, postType: string) => find(root, `[data-type="${postType}"]`)

describe('SectionView', () => {
    it('reads the sections the template placed, in their order, with their limit or the root\'s', (t) => {
        const { views } = sections(t, searchMarkup(searchSection('post', 2) + searchSection('product')))

        assert.deepEqual(views.map(({ searched }) => [searched.type.postType, searched.limit]), [['post', 2], ['product', 4]])
    })

    it('names a section whose type the root does not describe, and searches the others', (t) => {
        const { views, errors } = sections(t, searchMarkup(searchSection('page') + searchSection('post', 0)))

        assert.deepEqual(views.map(({ searched }) => [searched.type.postType, searched.limit]), [['post', 4]])
        assert.deepEqual(errors, ['[meilifacets] the search "search" describes no type "page".'])
    })

    it('shows the count the engine gave and a card per hit, highlighted', (t) => {
        const { root, views } = sections(t)
        const [products] = views

        products?.show({ hits: [hit(116, 'Sérum Éclat', { title: `${OPENING}Sér${CLOSING}um Éclat` })], totalHits: 12 })

        const shown = section(root, 'product')

        assert.equal(shown.hidden, false)
        assert.equal(find(shown, Contract.selector('search-count')).textContent, '12 results')
        assert.equal(find(shown, Contract.selector('title')).innerHTML, '<mark>Sér</mark>um Éclat')
        assert.equal(find<HTMLAnchorElement>(shown, Contract.selector('url')).href, 'https://example.test/116')
        assert.deepEqual(products?.count, { heading: 'Products', total: 12 })
        assert.equal(products?.options.length, 1)
    })

    it('shows a summary only when the card carries one', (t) => {
        const { root, views } = sections(t)
        const [, posts] = views

        posts?.show({ hits: [{ card: { title: 'Guide', summary: 'How to choose' } }, { card: { title: 'News' } }], totalHits: 2 })

        const summaries = [...section(root, 'post').querySelectorAll<HTMLElement>(Contract.selector('summary'))]

        assert.deepEqual(summaries.map((summary) => [summary.textContent, summary.hidden]), [['How to choose', false], ['', true]])
    })

    it('hides a section whose type found nothing, and offers no option from it', (t) => {
        const { root, views } = sections(t)
        const [products] = views

        products?.show({ hits: [hit(1, 'Sérum')], totalHits: 1 })
        products?.show({ hits: [], totalHits: 0 })

        assert.equal(section(root, 'product').hidden, true)
        assert.equal(products?.options.length, 0)
        assert.deepEqual(products?.count, { heading: 'Products', total: 0 })
    })

    it('hides itself and forgets its count when told to', (t) => {
        const { root, views } = sections(t)
        const [products] = views

        products?.show({ hits: [hit(1, 'Sérum')], totalHits: 1 })
        products?.hide()

        assert.equal(section(root, 'product').hidden, true)
        assert.deepEqual(products?.count, { heading: 'Products', total: 0 })
    })
})
