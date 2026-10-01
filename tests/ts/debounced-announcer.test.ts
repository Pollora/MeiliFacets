import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { ANNOUNCE_DELAY_MS, DebouncedAnnouncer } from '../../resources/assets/ts/shared/debounced-announcer.ts'
import { find, load, nextTurn } from './dom.ts'

import type { TestContext } from 'node:test'

const announcing = (t: TestContext) => {
    t.mock.timers.enable({ apis: ['setTimeout'] })

    const window = load('<p aria-live="polite"></p>')
    const region = find(window.document, '[aria-live]')
    const writes: string[] = []

    new window.MutationObserver(() => writes.push(region.textContent)).observe(region, { childList: true, characterData: true, subtree: true })

    return { announcer: new DebouncedAnnouncer(region), writes, region }
}

describe('DebouncedAnnouncer', () => {
    it('waits one second', () => {
        assert.equal(ANNOUNCE_DELAY_MS, 1000)
    })

    it('says nothing until the delay has passed, then says the last sentence once', async (t) => {
        const { announcer, writes } = announcing(t)

        announcer.announce('Products: 1 result')
        t.mock.timers.tick(ANNOUNCE_DELAY_MS - 1)
        announcer.announce('Products: 2 results')
        t.mock.timers.tick(ANNOUNCE_DELAY_MS - 1)
        await nextTurn()
        assert.deepEqual(writes, [])

        t.mock.timers.tick(1)
        await nextTurn()
        assert.deepEqual(writes, ['Products: 2 results'])
    })

    it('waits again at every keystroke while a sentence is pending', async (t) => {
        const { announcer, writes } = announcing(t)

        announcer.announce('Products: 1 result')
        t.mock.timers.tick(ANNOUNCE_DELAY_MS - 1)
        announcer.postpone()
        t.mock.timers.tick(ANNOUNCE_DELAY_MS - 1)
        await nextTurn()
        assert.deepEqual(writes, [])

        t.mock.timers.tick(1)
        await nextTurn()
        assert.deepEqual(writes, ['Products: 1 result'])
    })

    it('never writes the sentence it wrote last', async (t) => {
        const { announcer, writes } = announcing(t)

        for (const sentence of ['Products: 1 result', 'Products: 1 result', 'Products: 2 results', 'Products: 2 results']) {
            announcer.announce(sentence)
            t.mock.timers.tick(ANNOUNCE_DELAY_MS)
            await nextTurn()
        }

        assert.deepEqual(writes, ['Products: 1 result', 'Products: 2 results'])
    })

    it('starts no delay on a keystroke when nothing is pending', (t) => {
        const { announcer } = announcing(t)
        const delays = t.mock.method(globalThis, 'setTimeout')

        announcer.postpone()

        assert.equal(delays.mock.callCount(), 0)
    })

    it('drops the pending sentence once forgotten: the delay no longer writes it', async (t) => {
        const { announcer, writes } = announcing(t)

        announcer.announce('Products: 1 result')
        t.mock.timers.tick(ANNOUNCE_DELAY_MS - 1)
        announcer.forget()
        t.mock.timers.tick(ANNOUNCE_DELAY_MS)
        await nextTurn()

        assert.deepEqual(writes, [])
    })

    it('says again, once forgotten, the sentence it had said last', (t) => {
        const { announcer, region } = announcing(t)

        announcer.announce('Products: 1 result')
        t.mock.timers.tick(ANNOUNCE_DELAY_MS)
        announcer.forget()
        const heard = [region.textContent]

        announcer.announce('Products: 1 result')
        t.mock.timers.tick(ANNOUNCE_DELAY_MS)
        heard.push(region.textContent)

        assert.deepEqual(heard, ['', 'Products: 1 result'])
    })

    it('writes at once for a gesture that is not typing, and drops what was pending', async (t) => {
        const { announcer, writes } = announcing(t)

        announcer.announce('Products: 1 result')
        announcer.writeNow('Products: 2 results')
        t.mock.timers.tick(ANNOUNCE_DELAY_MS)
        await nextTurn()

        assert.deepEqual(writes, ['Products: 2 results'])
    })

    /** A counter the server rendered already says its count: saying it again would be heard twice. */
    it('takes what the region already says as said', async (t) => {
        t.mock.timers.enable({ apis: ['setTimeout'] })

        const window = load('<p aria-live="polite">4 items</p>')
        const region = find(window.document, '[aria-live]')
        const writes: string[] = []

        new window.MutationObserver(() => writes.push(region.textContent)).observe(region, { childList: true, characterData: true, subtree: true })
        new DebouncedAnnouncer(region).writeNow('4 items')
        await nextTurn()

        assert.deepEqual(writes, [])
    })
})
