import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { Highlight } from '../../resources/assets/ts/site-search/highlight.ts'
import { find } from './dom.ts'
import { CLOSING, OPENING, openSearch } from './site-search-fixtures.ts'

const highlighted = (formatted: Record<string, unknown> | undefined) => {
    const { root } = openSearch()
    const template = find<HTMLTemplateElement>(root, Contract.selector('search-card-template'))
    const card = find(template.content, Contract.selector('card'))
    const title = find(card, Contract.selector('title'))
    const summary = find(card, Contract.selector('summary'))

    title.textContent = 'Plain title'
    summary.textContent = 'Plain summary'
    new Highlight(new Contract(root)).show(card, formatted)

    return { card, title, summary }
}

describe('Highlight', () => {
    it('wraps what the engine tagged in <mark>, the rest as text', () => {
        const { title } = highlighted({ title: `${OPENING}Sér${CLOSING}um Éclat ${OPENING}Sér${CLOSING}` })

        assert.equal(title.innerHTML, '<mark>Sér</mark>um Éclat <mark>Sér</mark>')
    })

    it('never parses markup the engine returned', () => {
        const { title, summary } = highlighted({
            title: `<script>alert(1)</script> ${OPENING}<b>Sérum</b>${CLOSING}`,
            summary: `<img src=x onerror=alert(1)>`,
        })

        assert.equal(title.querySelector('script, b') === null, true)
        assert.equal(title.textContent, '<script>alert(1)</script> <b>Sérum</b>')
        assert.equal(title.querySelector('mark')?.textContent, '<b>Sérum</b>')
        assert.equal(summary.querySelector('img') === null, true)
        assert.equal(summary.textContent, '<img src=x onerror=alert(1)>')
    })

    it('reads the title and the summary only, never the link or the price', () => {
        const { card } = highlighted({
            url: `https://example.test/${OPENING}serum${CLOSING}`,
            price: `<span>${OPENING}12${CLOSING} €</span>`,
        })

        assert.equal(find(card, Contract.selector('url')).getAttribute('href'), '')
        assert.equal(find(card, Contract.selector('price')).innerHTML, '')
    })

    it('leaves the plain text where the engine formatted nothing', () => {
        const { title, summary } = highlighted(undefined)

        assert.equal(title.textContent, 'Plain title')
        assert.equal(summary.textContent, 'Plain summary')
        assert.equal(highlighted({ title: '', summary: 3 }).title.textContent, 'Plain title')
    })
})
