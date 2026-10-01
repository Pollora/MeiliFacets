import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { SectionView } from '../../resources/assets/ts/site-search/section-view.ts'
import { find } from './dom.ts'
import { hit, OPENING, CLOSING, openSearch, searchDescribed, searchMarkup, searchSection } from './site-search-fixtures.ts'

import type { TestContext } from 'node:test'

const sections = (t: TestContext, markup = searchMarkup(), seeAllParameter = 'q') => {
    const errors = t.mock.method(console, 'error', () => undefined)
    const { root } = openSearch(markup)
    const views = SectionView.allIn(new Contract(root), searchDescribed({ seeAllParameter }))

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

        products?.show({ hits: [hit(116, 'Sérum visage', { title: `${OPENING}Sér${CLOSING}um visage` })], totalHits: 12 }, 'se')

        const shown = section(root, 'product')

        assert.equal(shown.hidden, false)
        assert.equal(find(shown, Contract.selector('search-count')).textContent, '12 results')
        assert.equal(find(shown, Contract.selector('title')).innerHTML, '<mark>Sér</mark>um visage')
        assert.equal(find<HTMLAnchorElement>(shown, Contract.selector('url')).href, 'https://example.test/116')
        assert.deepEqual(products?.count, { heading: 'Products', total: 12 })
        assert.equal(products?.options.length, 1)
    })

    it('leaves the Tab order to the field, and the image of a result silent: the link already says the title', (t) => {
        const { root, views } = sections(t)
        const [products] = views
        const card = { title: 'Sérum visage', url: 'https://example.test/116', image_url: 'https://example.test/116.jpg', image_alt: 'Un flacon' }

        products?.show({ hits: [{ ID: 116, card }], totalHits: 1 }, 'se')

        const shown = section(root, 'product')

        assert.equal(find(shown, Contract.selector('url')).getAttribute('tabindex'), '-1')
        assert.equal(find<HTMLImageElement>(shown, Contract.selector('image')).hidden, false)
        assert.equal(find<HTMLImageElement>(shown, Contract.selector('image')).alt, '')
    })

    it('shows a summary only when the card carries one', (t) => {
        const { root, views } = sections(t)
        const [, posts] = views

        posts?.show({ hits: [{ card: { title: 'Guide', summary: 'How to choose' } }, { card: { title: 'News' } }], totalHits: 2 }, 'se')

        const summaries = [...section(root, 'post').querySelectorAll<HTMLElement>(Contract.selector('summary'))]

        assert.deepEqual(summaries.map((summary) => summary.textContent), ['How to choose'])
    })

    it('hides a section whose type found nothing, and offers no option from it', (t) => {
        const { root, views } = sections(t)
        const [products] = views

        products?.show({ hits: [hit(1, 'Sérum')], totalHits: 1 }, 'se')
        products?.show({ hits: [], totalHits: 0 }, 'se')

        assert.equal(section(root, 'product').hidden, true)
        assert.equal(products?.options.length, 0)
        assert.deepEqual(products?.count, { heading: 'Products', total: 0 })
    })

    it('hides itself and forgets its count when told to', (t) => {
        const { root, views } = sections(t)
        const [products] = views

        products?.show({ hits: [hit(1, 'Sérum')], totalHits: 1 }, 'se')
        products?.hide()

        assert.equal(section(root, 'product').hidden, true)
        assert.deepEqual(products?.count, { heading: 'Products', total: 0 })
    })

    it('keeps the node of a card another term finds again, and rewrites only its words', (t) => {
        const { root, views } = sections(t)
        const [products] = views

        products?.show({ hits: [hit(1, 'Sérum', { title: `${OPENING}S${CLOSING}érum` }), hit(2, 'Savon')], totalHits: 2 }, 'se')
        const [serum, soap] = products?.options ?? []

        products?.show({ hits: [hit(1, 'Sérum', { title: `${OPENING}Sér${CLOSING}um` })], totalHits: 1 }, 'se')
        const [kept] = products?.options ?? []

        assert.ok(kept !== undefined && kept === serum, 'the same node')
        assert.equal(find(section(root, 'product'), Contract.selector('title')).innerHTML, '<mark>Sér</mark>um')
        assert.equal(soap?.isConnected, false)
        assert.equal(section(root, 'product').querySelectorAll(Contract.selector('card')).length, 1)
    })

    it('puts the cards in the order of the answer, moving only those out of place', (t) => {
        const { root, views } = sections(t)
        const [products] = views
        const titles = () => [...section(root, 'product').querySelectorAll(Contract.selector('title'))].map((title) => title.textContent)

        products?.show({ hits: [hit(1, 'One'), hit(2, 'Two'), hit(3, 'Three')], totalHits: 3 }, 'se')
        const [one] = products?.options ?? []
        const moved = t.mock.method(section(root, 'product').querySelector(Contract.selector('search-results')) as Element, 'insertBefore')

        products?.show({ hits: [hit(3, 'Three'), hit(1, 'One'), hit(4, 'Four')], totalHits: 3 }, 'se')

        assert.deepEqual(titles(), ['Three', 'One', 'Four'])
        assert.ok(products?.options[1] === one, 'the same node, one rank down')
        assert.equal(moved.mock.callCount(), 2)
    })

    it('draws a card the engine gave no identity afresh every time', (t) => {
        const { views } = sections(t)
        const [products] = views

        products?.show({ hits: [{ card: { title: 'Nameless' } }], totalHits: 1 }, 'se')
        const [first] = products?.options ?? []
        products?.show({ hits: [{ card: { title: 'Nameless' } }], totalHits: 1 }, 'se')
        const [second] = products?.options ?? []

        assert.ok(first !== undefined && second !== undefined && first !== second, 'two nodes')
        assert.equal(first?.isConnected, false)
    })

    it('keeps the cards of a section it hides, and finds them again when it shows it', (t) => {
        const { views } = sections(t)
        const [products] = views

        products?.show({ hits: [hit(1, 'Sérum')], totalHits: 1 }, 'se')
        const [serum] = products?.options ?? []
        products?.show({ hits: [], totalHits: 0 }, 'se')
        products?.show({ hits: [hit(1, 'Sérum')], totalHits: 1 }, 'se')

        assert.ok(products?.options[0] === serum, 'the same node')
    })

    it('leaves alone a card found again with the same words, and a count that did not change', async (t) => {
        const { root, views } = sections(t)
        const [products] = views

        products?.show({ hits: [hit(1, 'Sérum')], totalHits: 1 }, 'se')
        const title = find(section(root, 'product'), Contract.selector('title'))
        const count = find(section(root, 'product'), Contract.selector('search-count'))
        const titleWrites = t.mock.method(title, 'replaceChildren')
        let countWrites = 0

        const view = root.ownerDocument.defaultView as Window & typeof globalThis

        new view.MutationObserver(() => countWrites++).observe(count, { childList: true, characterData: true, subtree: true })
        products?.show({ hits: [hit(1, 'Sérum')], totalHits: 1 }, 'se')
        await new Promise((resolve) => setImmediate(resolve))

        assert.equal(titleWrites.mock.callCount(), 0)
        assert.equal(countWrites, 0)
    })

    /** « See all » opens the archive filtered by the term, so it finds what the count counted. */
    it('leads « see all » to the archive with the term the count is for, encoded', (t) => {
        const { root, views } = sections(t)
        const [products] = views
        const link = find<HTMLAnchorElement>(section(root, 'product'), Contract.selector('search-see-all'))

        products?.show({ hits: [hit(1, 'Crème')], totalHits: 1 }, 'crème & co+')

        assert.equal(link.href, 'https://example.test/product?q=cr%C3%A8me+%26+co%2B')
    })

    it('writes the term under the name the project gave the parameter', (t) => {
        const { root, views } = sections(t, searchMarkup(), 'recherche')
        const [products] = views
        const link = find<HTMLAnchorElement>(section(root, 'product'), Contract.selector('search-see-all'))

        products?.show({ hits: [hit(1, 'Sérum')], totalHits: 1 }, 'ser')

        assert.equal(link.href, 'https://example.test/product?recherche=ser')
    })

    it('keeps the query the archive already carries, and leads back to the bare archive once hidden', (t) => {
        const markup = searchMarkup(searchSection('product').replace('https://example.test/product', '/?post_type=product'))
        const { root, views } = sections(t, markup)
        const [products] = views
        const link = find<HTMLAnchorElement>(section(root, 'product'), Contract.selector('search-see-all'))

        products?.show({ hits: [hit(1, 'Sérum')], totalHits: 1 }, 'ser')
        assert.equal(link.getAttribute('href'), 'https://example.test/?post_type=product&q=ser')

        products?.hide()
        assert.equal(link.getAttribute('href'), '/?post_type=product')
    })
})
