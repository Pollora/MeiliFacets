import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { CardView } from '../../resources/assets/ts/results/card-view.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { find, listingMarkup, open } from './dom.ts'

describe('CardView', () => {
    const shown = (card: Record<string, unknown>) => {
        const { root } = open(listingMarkup())
        const template = find<HTMLTemplateElement>(root, Contract.selector('card-template'))
        const node = find(template.content, Contract.selector('card'))

        return new CardView(new Contract(root)).show(node, card)
    }

    it('writes a figure as text', () => {
        assert.equal(find(shown({ title: 12 }), Contract.selector('title')).textContent, '12')
    })

    it('names an image after the product when its alt text is empty', () => {
        const image = (card: Record<string, unknown>) => find<HTMLImageElement>(shown(card), Contract.selector('image'))

        assert.equal(image({ title: 'Crème Hydratante Riche', image_alt: '' }).alt, 'Crème Hydratante Riche')
        assert.equal(image({ title: 'Crème Hydratante Riche', image_alt: 'Un pot ouvert' }).alt, 'Un pot ouvert')
    })

    it('writes nothing for a field that is neither text nor a figure', () => {
        const card = shown({ title: { fr: 'Crème' }, url: ['https://example.test/creme'], image_alt: true })

        assert.equal(find(card, Contract.selector('title')).textContent, '')
        assert.equal(find<HTMLAnchorElement>(card, Contract.selector('url')).getAttribute('href'), '')
        assert.equal(find<HTMLImageElement>(card, Contract.selector('image')).alt, '')
    })
})
