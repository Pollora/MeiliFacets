import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { SearchTermInput } from '../../resources/assets/ts/shared/search-term-input.ts'
import { find, load } from './dom.ts'
import { typeInto } from './site-search-fixtures.ts'

import type { TestContext } from 'node:test'

const typing = (t: TestContext, value = '') => {
    t.mock.timers.enable({ apis: ['setTimeout'] })

    const window = load(`<input value="${value}">`)
    const input = find<HTMLInputElement>(window.document, 'input')
    const heard: string[] = []

    const terms = new SearchTermInput(input, { minChars: 2, delay: 120 }).start({
        search: (term) => heard.push(term),
        clear: () => heard.push('(cleared)'),
    })

    return { heard, input, terms, type: (term: string) => typeInto(window, input, term) }
}

describe('SearchTermInput', () => {
    it('searches once the visitor has paused for the delay, and not before', (t) => {
        const { heard, type } = typing(t)

        type('se')
        t.mock.timers.tick(119)
        assert.deepEqual(heard, [])

        t.mock.timers.tick(1)
        assert.deepEqual(heard, ['se'])
    })

    it('searches only the last of the terms typed within the delay', (t) => {
        const { heard, type } = typing(t)

        type('se')
        t.mock.timers.tick(60)
        type('ser')
        t.mock.timers.tick(60)
        type('seru')
        t.mock.timers.tick(120)

        assert.deepEqual(heard, ['seru'])
    })

    it('clears under the threshold, counted once trimmed and in code points', (t) => {
        const { heard, type } = typing(t)

        type(' s ')
        type('🧴')
        t.mock.timers.tick(120)

        assert.deepEqual(heard, ['(cleared)', '(cleared)'])
        assert.equal(SearchTermInput.isSearchable('é1', 2), true)
    })

    it('never searches a term holding no letter and no figure (R-159)', (t) => {
        const { heard, type } = typing(t)

        for (const term of ['?(', '  ', '--', '🧴🧴']) {
            type(term)
            t.mock.timers.tick(120)
        }

        assert.deepEqual(heard, ['(cleared)', '(cleared)', '(cleared)', '(cleared)'])
        assert.equal(SearchTermInput.isSearchable('  ', 2), false)
    })

    it('does not search again for a term that only gained spaces', (t) => {
        const { heard, type } = typing(t)

        type('serum')
        t.mock.timers.tick(120)
        type('serum ')
        t.mock.timers.tick(120)

        assert.deepEqual(heard, ['serum'])
    })

    it('searches what was typed before it started', (t) => {
        const { heard } = typing(t, 'creme')

        t.mock.timers.tick(120)

        assert.deepEqual(heard, ['creme'])
    })

    it('takes a term the page was served with as searched, whatever its length', (t) => {
        t.mock.timers.enable({ apis: ['setTimeout'] })

        const window = load('<input value="a">')
        const input = find<HTMLInputElement>(window.document, 'input')
        const heard: string[] = []

        new SearchTermInput(input, { minChars: 2, delay: 120 }).startFromServedTerm({
            search: (term) => heard.push(term),
            clear: () => heard.push('(cleared)'),
        })
        t.mock.timers.tick(120)
        assert.deepEqual(heard, [])

        typeInto(window, input, 'ab')
        t.mock.timers.tick(120)
        assert.deepEqual(heard, ['ab'])
    })

    it('shows a term searched elsewhere without searching it, and drops the one pending', (t) => {
        const { heard, input, terms, type } = typing(t)

        type('ser')
        terms.show('')
        t.mock.timers.tick(120)

        assert.equal(input.value, '')
        assert.deepEqual(heard, [])
    })

    it('searches again a term typed after the field was rewritten', (t) => {
        const { heard, terms, type } = typing(t)

        type('ser')
        t.mock.timers.tick(120)
        terms.show('')
        type('ser')
        t.mock.timers.tick(120)

        assert.deepEqual(heard, ['ser', 'ser'])
    })
})
