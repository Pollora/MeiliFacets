import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { NewPills } from '../../resources/assets/ts/listing/new-pills.ts'
import { load } from './dom.ts'

import type { TestWindow } from './dom.ts'

describe('NewPills', () => {
    let window: TestWindow

    const pill = (kind: string, name: string, value: string, label = value) => {
        const button = window.document.createElement('button')

        button.setAttribute('data-kind', kind)
        button.setAttribute('name', name)
        button.setAttribute('value', value)
        button.textContent = label

        return button
    }

    const namesOf = (pills: Element[]) => pills.map((shown) => shown.textContent)

    beforeEach(() => {
        window = load('')
    })

    it('names the pills a redraw brought in, not the ones it rebuilt', () => {
        const newPills = new NewPills([pill('term', 'brand', 'acme')])

        assert.deepEqual(namesOf(newPills.among([pill('term', 'brand', 'acme'), pill('term', 'brand', 'globex')])), ['globex'])
    })

    it('knows a pill by the filter it takes off, not by its label', () => {
        const newPills = new NewPills([pill('price', 'price', '', '10 € – 20 €')])

        assert.deepEqual(namesOf(newPills.among([pill('price', 'price', '', '10 € – 40 €')])), [])
    })

    it('tells apart the same value under two parameters or two kinds', () => {
        const newPills = new NewPills([pill('term', 'brand', 'acme')])

        assert.deepEqual(namesOf(newPills.among([pill('term', 'category', 'acme', 'category'), pill('price', 'brand', 'acme', 'price')])), ['category', 'price'])
    })

    it('finds every pill new when none was shown', () => {
        assert.deepEqual(namesOf(new NewPills([]).among([pill('term', 'brand', 'acme')])), ['acme'])
    })
})
