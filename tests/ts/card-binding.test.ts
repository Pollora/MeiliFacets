import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'

import { PageWindow } from '../../resources/assets/ts/pagination/page-window.ts'
import { CardFieldAttribute } from '../../resources/assets/ts/results/card-field-attribute.ts'
import { CardFieldValue } from '../../resources/assets/ts/results/card-field-value.ts'
import { CardBinding } from '../../resources/assets/ts/results/card-binding.ts'
import { ResultsView } from '../../resources/assets/ts/results/results-view.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { find, listingMarkup, load, open } from './dom.ts'

interface Expected {
    text: string
    elements: number
    attributes: Record<string, string | null>
    classes: string[]
    present: boolean
}

interface ElementCase {
    case: string
    template: string
    card: Record<string, unknown>
    expected: Expected
}

interface Cases {
    attributes: { allowed: string[], refused: string[] }
    urls: { safe: string[], unsafe: string[] }
    urlLists: { safe: string[], unsafe: string[] }
    dimensions: { accepted: string[], refused: string[] }
    texts: { value: unknown, text: string }[]
    fields: { refused: string[] }
    elements: ElementCase[]
}

/** The server half is `tests/Unit/CardBindingTest.php`: same cases, and it renders these templates for an empty card. */
const cases = JSON.parse(readFileSync(new URL('../card-binding-cases.json', import.meta.url), 'utf8')) as Cases

const element = (markup: string) => {
    const window = load(`<div id="card">${markup}</div>`)

    return find(window.document, '#card')
}

const bound = (markup: string, card: Record<string, unknown>) => {
    const host = element(markup)
    new CardBinding(card).apply(host)

    return host.firstElementChild
}

const kept = (markup: string, card: Record<string, unknown>) => {
    const node = bound(markup, card)

    assert.ok(node !== null)

    return node
}

const classesOf = (node: Element) => [...node.classList].sort()

describe('CardBinding', () => {
    describe('writes what the server renders', () => {
        for (const { case: name, template, card, expected } of cases.elements) {
            it(name, () => {
                const node = bound(template, card)

                assert.equal(node !== null, expected.present)

                if (node === null) {
                    return
                }

                assert.equal(node.textContent, expected.text)
                assert.equal(node.children.length, expected.elements)
                assert.deepEqual(classesOf(node), expected.classes)

                for (const [attribute, value] of Object.entries(expected.attributes)) {
                    assert.equal(node.getAttribute(attribute), value, attribute)
                }
            })
        }
    })

    it('binds the node it is given as well as the ones it holds', () => {
        const host = element('<a data-meili-attr="data-product_id:id"><span data-meili-text="brand"></span></a>')
        const link = find(host, 'a')

        new CardBinding({ id: 12, brand: 'Acme' }).apply(link)

        assert.equal(link.getAttribute('data-product_id'), '12')
        assert.equal(find(link, 'span').textContent, 'Acme')
    })

    it('removes a node already written when the next card lacks the field', () => {
        assert.equal(bound('<span data-meili-text="brand" data-meili-attr="title:brand">Acme</span>', {}) === null, true)
    })

    it('removes the elements a card has nothing to show in, and keeps the others', () => {
        const host = element('<a data-meili-attr="href:url"><span data-meili-text="brand"></span><b data-meili-if="!brand">Kept</b></a>')

        new CardBinding({ url: '/creme' }).apply(host)

        assert.equal(host.innerHTML, '<a data-meili-attr="href:url" href="/creme"><b data-meili-if="!brand">Kept</b></a>')
    })

    it('skips a binding written by hand that the server would refuse', () => {
        const node = kept(
            '<a href="/creme" data-meili="url" data-meili-attr="onclick:code style:code data-meili:code href:code x-data:code">Crème</a>',
            { code: 'alert(1)' }
        )

        assert.equal(node.hasAttribute('onclick'), false)
        assert.equal(node.hasAttribute('style'), false)
        assert.equal(node.hasAttribute('x-data'), false)
        assert.equal(node.getAttribute('data-meili'), 'url')
        assert.equal(node.getAttribute('href'), 'alert(1)')
    })

    it('skips a malformed pair or field rather than guessing', () => {
        const node = kept('<a class="on" data-meili-class="on:!flag :flag on" data-meili-text="bad field">kept</a>', { flag: false })

        assert.deepEqual(classesOf(node), ['on'])
        assert.equal(node.textContent, 'kept')
    })

    for (const field of cases.fields.refused) {
        it(`skips the field name ${JSON.stringify(field)}, which the server refuses`, () => {
            const host = element('<span>kept</span>')
            const node = find(host, 'span')
            node.setAttribute('data-meili-text', field)
            node.setAttribute('data-meili-attr', `title:${field}`)
            node.setAttribute('data-meili-if', field)

            new CardBinding({ [field]: 'written' }).apply(host)

            assert.equal(host.firstElementChild === node, true)
            assert.equal(node.textContent, 'kept')
            assert.equal(node.hasAttribute('title'), false)
        })
    }

    it('never reads a field the card does not own', () => {
        assert.equal(bound('<span data-meili-text="constructor"></span>', {}) === null, true)
        assert.equal(bound('<span data-meili-if="toString">Shown</span>', {}) === null, true)
    })

    it('writes the identifier before the card reaches the page', () => {
        const { root } = open(listingMarkup())
        const template = find<HTMLTemplateElement>(root, Contract.selector('card-template'))
        find(template.content, Contract.selector('card')).setAttribute('data-meili-attr', 'data-product-id:id')
        const list = find(root, Contract.selector('results'))
        const atInsertion: (string | null)[] = []
        const replaceChildren = list.replaceChildren.bind(list)

        list.replaceChildren = (...nodes: (Node | string)[]) => {
            atInsertion.push(...nodes.map((node) => (node instanceof Element ? node.getAttribute('data-product-id') : null)))
            replaceChildren(...nodes)
        }

        new ResultsView(new Contract(root)).show([{ id: 12 }, { id: 13 }], new PageWindow({ asked: 1, perPage: 16, total: 2, reachable: 1_000 }))

        assert.deepEqual(atInsertion, ['12', '13'])
    })
})

describe('CardFieldAttribute', () => {
    for (const name of cases.attributes.allowed) {
        it(`binds ${name}`, () => assert.equal(new CardFieldAttribute(name).isAllowed, true))
    }

    for (const name of cases.attributes.refused) {
        it(`refuses ${name}`, () => assert.equal(new CardFieldAttribute(name).isAllowed, false))
    }

    for (const url of cases.urls.safe) {
        it(`writes ${JSON.stringify(url)}`, () => assert.equal(new CardFieldAttribute('href').accepts(url), true))
    }

    for (const url of cases.urls.unsafe) {
        it(`refuses ${JSON.stringify(url)}`, () => assert.equal(new CardFieldAttribute('href').accepts(url), false))
    }

    it('writes no empty value, since an empty href links the current page', () => assert.equal(new CardFieldAttribute('href').accepts(''), false))

    for (const list of cases.urlLists.safe) {
        it(`writes the srcset ${JSON.stringify(list)}`, () => assert.equal(new CardFieldAttribute('srcset').accepts(list), true))
    }

    for (const list of cases.urlLists.unsafe) {
        it(`refuses the srcset ${JSON.stringify(list)}`, () => assert.equal(new CardFieldAttribute('srcset').accepts(list), false))
    }

    for (const size of cases.dimensions.accepted) {
        it(`writes the width ${JSON.stringify(size)}`, () => assert.equal(new CardFieldAttribute('width').accepts(size), true))
    }

    for (const size of cases.dimensions.refused) {
        it(`refuses the width ${JSON.stringify(size)}`, () => assert.equal(new CardFieldAttribute('width').accepts(size), false))
    }
})

describe('CardFieldValue', () => {
    for (const { value, text } of cases.texts) {
        it(`writes ${JSON.stringify(value)} as ${JSON.stringify(text)}`, () => assert.equal(new CardFieldValue(value).text, text))
    }

    it('writes nothing for a number JSON cannot carry', () => {
        assert.equal(new CardFieldValue(Number.NaN).text, '')
        assert.equal(new CardFieldValue(Number.POSITIVE_INFINITY).text, '')
    })
})
