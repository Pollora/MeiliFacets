import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { CONTRACT, load } from './dom.ts'

const window = load('')

const node = (hook: string | null = null, children: Element[] = []) => {
    const element = window.document.createElement('div')

    if (hook !== null) {
        element.setAttribute('data-meili', hook)
    }

    element.append(...children)

    return element
}

const template = (...hooks: string[]) => {
    const element = window.document.createElement('template')

    element.setAttribute('data-meili', 'card-template')
    element.content.append(...hooks.map((hook) => node(hook)))

    return element
}

const versioned = (root: Element, version: number) => {
    root.setAttribute('data-meili-contract', String(version))

    return root
}

const complete = () => versioned(node(null, [
    node('results', [node('card')]),
    node('empty'),
    template('card', 'url', 'image', 'title', 'price'),
    node('facets', [
        node('facet', [node('facet-value', [node('input'), node('count')]), node('more')]),
    ]),
    node('pagination', [node('previous'), node('page'), node('next')]),
    node('sort', [node('sort-trigger'), node('sort-list', [node('sort-option')])]),
]), CONTRACT)

const replaceChild = (root: Element, rank: number, replacement: Element) => {
    root.children[rank]?.replaceWith(replacement)
}

describe('Contract', () => {
    it('accepts a complete listing', () => {
        assert.deepEqual(new Contract(complete()).breaches(), [])
    })

    it('refuses a version it does not speak', () => {
        const root = versioned(complete(), CONTRACT + 1)

        assert.deepEqual(new Contract(root).breaches(), [`contract ${CONTRACT + 1}, expected ${CONTRACT}`])
    })

    it('refuses an unversioned root before looking at anything else', () => {
        assert.deepEqual(new Contract(node()).breaches(), [`contract absent, expected ${CONTRACT}`])
    })

    it('names every structural hook the theme dropped', () => {
        const root = complete()
        root.querySelector('[data-meili="empty"]')?.remove()

        assert.deepEqual(new Contract(root).breaches(), ['empty'])
    })

    it('looks inside the card template, not around it', () => {
        const root = complete()
        replaceChild(root, 2, template('card', 'url', 'image', 'title'))

        assert.deepEqual(new Contract(root).breaches(), ['card-template > price'])
    })

    it('requires an input only where a facet value is rendered', () => {
        const root = complete()
        replaceChild(root, 3, node('facets'))

        assert.deepEqual(new Contract(root).breaches(), [])
    })

    // A leaf category and a filtered listing with no results both render a facet block holding nothing.
    it('accepts a facet block the data left empty', () => {
        const root = complete()
        replaceChild(root, 3, node('facets', [node('facet', [node('more')])]))

        assert.deepEqual(new Contract(root).breaches(), [])
    })

    it('refuses a facet block without its fold button', () => {
        const root = versioned(node(null, [
            node('results', [node('card')]),
            node('empty'),
            template('card', 'url', 'image', 'title', 'price'),
            node('facets', [node('facet', [node('facet-value', [node('input')])])]),
        ]), CONTRACT)

        assert.deepEqual(new Contract(root).breaches(), ['facet > more'])
    })

    // A price control is placed like a facet and holds no values: a fold button
    // there would exist only to satisfy this check.
    it('asks no fold button of a block with nothing to fold', () => {
        const root = complete()
        const range = node('price-range', [node('price-track'), node('price-handle')])
        replaceChild(root, 3, node('facets', [node('facet', [range])]))

        assert.deepEqual(new Contract(root).breaches(), [])
    })

    it('names a second block breaching, not only the first', () => {
        const root = complete()
        replaceChild(root, 3, node('facets', [
            node('facet', [node('facet-value', [node('input')]), node('more')]),
            node('facet', [node('facet-value', [node('input')])]),
        ]))

        assert.deepEqual(new Contract(root).breaches(), ['facet > more'])
    })

    it('refuses a facet value without its input', () => {
        const root = complete()
        replaceChild(root, 3, node('facets', [node('facet-value', [node('count')])]))

        assert.deepEqual(new Contract(root).breaches(), ['facet-value > input'])
    })

    it('refuses a sort control missing its listbox', () => {
        const root = complete()
        replaceChild(root, 5, node('sort', [node('sort-trigger')]))

        assert.deepEqual(new Contract(root).breaches(), ['sort > sort-list', 'sort > sort-option'])
    })

    it('refuses a list of pills with no template to draw them from, or a template with no pill', () => {
        const pills = (...children: Element[]) => {
            const root = complete()
            root.append(node('active-values', children))

            return new Contract(root).breaches()
        }
        const pillTemplate = (...hooks: string[]) => {
            const element = template(...hooks)
            element.setAttribute('data-meili', 'active-value-template')

            return element
        }

        assert.deepEqual(pills(pillTemplate('active-value')), [])
        assert.deepEqual(pills(node('active-value')), ['active-values > active-value-template'])
        assert.deepEqual(pills(pillTemplate()), ['active-value-template > active-value'])
    })

    it('ignores a sort the theme did not render', () => {
        const root = complete()
        root.children[5]?.remove()

        assert.deepEqual(new Contract(root).breaches(), [])
    })

    it('ignores a pagination the theme did not render', () => {
        const root = complete()
        root.children[4]?.remove()

        assert.deepEqual(new Contract(root).breaches(), [])
    })

    it('reads hooks under an explicit scope', () => {
        const contract = new Contract(complete())
        const card = contract.one('card-template')

        assert.ok(card)
        assert.equal(contract.all('title', card).length, 1)
        assert.equal(contract.all('card').length, 1)
    })
})

describe('a hook left outside every listing', () => {
    const inDocument = (markup: string) => {
        const window = load(markup)
        const roots = [...window.document.querySelectorAll('[data-listing]')]

        return Contract.orphans(window.document, roots)
    }

    it('is named once, and not through the hooks it contains', () => {
        assert.deepEqual(
            inDocument(`
                <fieldset data-meili="facet"><input data-meili="input"></fieldset>
                <fieldset data-meili="facet"><input data-meili="input"></fieldset>
                <div data-listing="products" data-meili-contract="${CONTRACT}"></div>`),
            ['facet']
        )
    })

    it('is named alongside the other loose hooks it does not contain', () => {
        assert.deepEqual(
            inDocument(`
                <fieldset data-meili="facet"></fieldset>
                <button data-meili="reset"></button>
                <div data-listing="products" data-meili-contract="${CONTRACT}"></div>`),
            ['facet', 'reset']
        )
    })

    it('is not named when it sits inside one', () => {
        assert.deepEqual(
            inDocument(`
                <div data-listing="products" data-meili-contract="${CONTRACT}">
                    <fieldset data-meili="facet"></fieldset>
                </div>`),
            []
        )
    })

    it('is not named when it sits inside the second of two listings', () => {
        assert.deepEqual(
            inDocument(`
                <div data-listing="products" data-meili-contract="${CONTRACT}"></div>
                <div data-listing="articles" data-meili-contract="${CONTRACT}">
                    <fieldset data-meili="facet"></fieldset>
                </div>`),
            []
        )
    })
})
