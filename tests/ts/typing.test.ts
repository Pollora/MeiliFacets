import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Typing } from '../../resources/assets/ts/site-search/typing.ts'
import { find, load } from './dom.ts'
import { typeInto } from './site-search-fixtures.ts'

import type { TestContext } from 'node:test'

const typing = (t: TestContext, value = '') => {
    t.mock.timers.enable({ apis: ['setTimeout'] })

    const window = load(`<input value="${value}">`)
    const input = find<HTMLInputElement>(window.document, 'input')
    const heard: string[] = []

    new Typing(input, { minChars: 2, delay: 120 }).start({
        search: (term) => heard.push(term),
        clear: () => heard.push('(cleared)'),
    })

    return { heard, type: (term: string) => typeInto(window, input, term) }
}

describe('Typing', () => {
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
        assert.equal(Typing.isSearchable('é1', 2), true)
    })

    it('never searches a term holding no letter and no figure (R-159)', (t) => {
        const { heard, type } = typing(t)

        for (const term of ['?(', '  ', '--', '🧴🧴']) {
            type(term)
            t.mock.timers.tick(120)
        }

        assert.deepEqual(heard, ['(cleared)', '(cleared)', '(cleared)', '(cleared)'])
        assert.equal(Typing.isSearchable('  ', 2), false)
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
})
