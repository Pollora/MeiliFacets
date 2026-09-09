import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/js/contract.js'
import { open } from './dom.js'

const HOOK = /^\[data-meili="([^"]+)"\]$/

/** Read from the client, so the fixtures follow an increment instead of failing on it. */
const CONTRACT = Number(/const VERSION = (\d+)/.exec(readFileSync(new URL('../../resources/assets/js/contract.js', import.meta.url), 'utf8'))[1])

/** Enough DOM for the one selector shape the contract builds. */
class Node {
    constructor(hook = null, children = [], { template = false } = {}) {
        this.hook = hook
        // A template's markup hangs off content only, out of reach of the tree above it.
        this.children = template ? [] : children
        this.content = template ? new Node(null, children) : null
        this.version = null
    }

    getAttribute(name) {
        return name === 'data-meili-contract' ? this.version : this.hook
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] ?? null
    }

    querySelectorAll(selector) {
        const hook = selector.match(HOOK)[1]

        return this.#descendants().filter((node) => node.hook === hook)
    }

    #descendants() {
        return this.children.flatMap((child) => [child, ...child.#descendants()])
    }
}

const template = (...hooks) => new Node('card-template', hooks.map((hook) => new Node(hook)), { template: true })

const complete = () => {
    const root = new Node(null, [
        new Node('results', [new Node('card')]),
        new Node('empty'),
        template('card', 'url', 'image', 'title', 'price'),
        new Node('facets', [
            new Node('facet', [new Node('facet-value', [new Node('input'), new Node('count')]), new Node('more')]),
        ]),
        new Node('pagination', [new Node('previous'), new Node('page'), new Node('next')]),
        new Node('sort', [new Node('sort-trigger'), new Node('sort-list', [new Node('sort-option')])]),
    ])
    root.version = String(CONTRACT)

    return root
}

describe('Contract', () => {
    it('accepts a complete listing', () => {
        assert.deepEqual(new Contract(complete()).breaches(), [])
    })

    it('refuses a version it does not speak', () => {
        const root = complete()
        root.version = String(CONTRACT + 1)

        assert.deepEqual(new Contract(root).breaches(), [`contract ${CONTRACT + 1}, expected ${CONTRACT}`])
    })

    it('refuses an unversioned root before looking at anything else', () => {
        assert.deepEqual(new Contract(new Node()).breaches(), [`contract absent, expected ${CONTRACT}`])
    })

    it('names every structural hook the theme dropped', () => {
        const root = complete()
        root.children = root.children.filter((child) => child.hook !== 'empty')

        assert.deepEqual(new Contract(root).breaches(), ['empty'])
    })

    it('looks inside the card template, not around it', () => {
        const root = complete()
        root.children[2] = template('card', 'url', 'image', 'title')

        assert.deepEqual(new Contract(root).breaches(), ['card-template > price'])
    })

    it('requires an input only where a facet value is rendered', () => {
        const root = complete()
        root.children[3] = new Node('facets')

        assert.deepEqual(new Contract(root).breaches(), [])
    })

    // A leaf category and a filtered listing with no results both render a facet block holding nothing.
    it('accepts a facet block the data left empty', () => {
        const root = complete()
        root.children[3] = new Node('facets', [new Node('facet', [new Node('more')])])

        assert.deepEqual(new Contract(root).breaches(), [])
    })

    it('refuses a facet block without its fold button', () => {
        const root = new Node(null, [
            new Node('results', [new Node('card')]),
            new Node('empty'),
            template('card', 'url', 'image', 'title', 'price'),
            new Node('facets', [new Node('facet', [new Node('facet-value', [new Node('input')])])]),
        ])
        root.version = String(CONTRACT)

        assert.deepEqual(new Contract(root).breaches(), ['facet > more'])
    })

    it('refuses a facet value without its input', () => {
        const root = complete()
        root.children[3] = new Node('facets', [new Node('facet-value', [new Node('count')])])

        assert.deepEqual(new Contract(root).breaches(), ['facet-value > input'])
    })

    it('refuses a sort control missing its listbox', () => {
        const root = complete()
        root.children[5] = new Node('sort', [new Node('sort-trigger')])

        assert.deepEqual(new Contract(root).breaches(), ['sort > sort-list', 'sort > sort-option'])
    })

    it('ignores a sort the theme did not render', () => {
        const root = complete()
        root.children.splice(5, 1)

        assert.deepEqual(new Contract(root).breaches(), [])
    })

    it('ignores a pagination the theme did not render', () => {
        const root = complete()
        root.children.splice(4, 1)

        assert.deepEqual(new Contract(root).breaches(), [])
    })

    it('reads hooks under an explicit scope', () => {
        const contract = new Contract(complete())
        const card = contract.one('card-template')

        assert.equal(contract.all('title', card).length, 1)
        assert.equal(contract.all('card').length, 1)
    })
})

describe('a hook left outside every listing', () => {
    const inDocument = (markup) => {
        const { window } = open(markup)
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
