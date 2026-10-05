import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { CardView } from '../../resources/assets/ts/results/card-view.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { find, listingMarkup, open } from './dom.ts'

const IMAGE = 'https://example.test/creme.jpg'

describe('CardView', () => {
    const shown = (card: Record<string, unknown>) => {
        const { root } = open(listingMarkup())
        const template = find<HTMLTemplateElement>(root, Contract.selector('card-template'))
        const node = find(template.content, Contract.selector('card'))

        return new CardView(new Contract(root)).show(node, card)
    }

    const has = (card: Element, hook: string) => card.querySelector(Contract.selector(hook)) !== null

    it('writes a figure as text', () => {
        assert.equal(find(shown({ title: 12 }), Contract.selector('title')).textContent, '12')
    })

    it('leaves out a title that is not a finite number', () => {
        assert.equal(has(shown({ title: Number.NaN }), 'title'), false)
    })

    it('names an image after the product when its alt text is empty', () => {
        const image = (card: Record<string, unknown>) => find<HTMLImageElement>(shown(card), Contract.selector('image'))

        assert.equal(image({ title: 'Crème Hydratante Riche', image_url: IMAGE, image_alt: '' }).alt, 'Crème Hydratante Riche')
        assert.equal(image({ title: 'Crème', image_url: IMAGE, image_alt: 'Un pot ouvert' }).alt, 'Un pot ouvert')
    })

    it('writes nothing for a field that is neither text nor a figure', () => {
        const card = shown({ title: { fr: 'Crème' }, url: ['https://example.test/creme'], image_url: IMAGE, image_alt: true })

        assert.equal(has(card, 'title'), false)
        assert.equal(find(card, Contract.selector('url')).hasAttribute('href'), false)
        assert.equal(find<HTMLImageElement>(card, Contract.selector('image')).hasAttribute('alt'), false)
    })

    it('leaves out the image and the price a card does not have', () => {
        const card = shown({ title: 'Crème' })

        assert.equal(has(card, 'image'), false)
        assert.equal(has(card, 'price'), false)
    })

    it('writes the price as the markup WooCommerce formatted', () => {
        assert.equal(find(shown({ price: '<bdi>12,00</bdi>' }), Contract.selector('price')).innerHTML, '<bdi>12,00</bdi>')
    })

    it('hands a card the id of its document, over any id the card holds', () => {
        assert.deepEqual(CardView.fieldsOf({ ID: 393, card: { title: 'Crème', id: 7 } }), { title: 'Crème', id: 393 })
        assert.deepEqual(CardView.fieldsOf({ card: { title: 'Crème' } }), { title: 'Crème' })
    })

    it('hands a card read off a variant the id of its product', () => {
        const hit = { ID: '393-1', parent_id: 393, card: { title: 'Crème' } }

        assert.deepEqual(CardView.fieldsOf(hit), { title: 'Crème', id: 393 })
    })
})
