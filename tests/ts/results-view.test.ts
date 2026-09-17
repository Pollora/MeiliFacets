import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { PageWindow } from '../../resources/assets/ts/pagination/page-window.ts'
import { ResultsView } from '../../resources/assets/ts/results/results-view.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { find, listingMarkup, open } from './dom.ts'

describe('ResultsView', () => {
    const pageWindow = new PageWindow({ asked: 1, perPage: 16, total: 2, reachable: 1_000 })

    it('paints no card when the card template holds no element', () => {
        const { root } = open(listingMarkup())
        find<HTMLTemplateElement>(root, Contract.selector('card-template')).content.replaceChildren()

        new ResultsView(new Contract(root)).show([{ title: 'Crème' }, { title: 'Savon' }], pageWindow)

        assert.equal(find(root, Contract.selector('results')).childNodes.length, 0)
    })

    it('paints one card per hit from the template', () => {
        const { root } = open(listingMarkup())

        new ResultsView(new Contract(root)).show([{ title: 'Crème' }, { title: 'Savon' }], pageWindow)

        assert.equal(find(root, Contract.selector('results')).children.length, 2)
    })
})
