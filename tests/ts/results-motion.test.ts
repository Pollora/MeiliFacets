import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { SiteSearch } from '../../resources/assets/ts/site-search/site-search.ts'
import { SEARCH_STYLESHEET, find, press } from './dom.ts'
import { FakeEngine, hit, openSearch, searchDescribed, settle, typeInto } from './site-search-fixtures.ts'

import type { TestContext } from 'node:test'
import type { SearchHit } from '../../resources/assets/ts/shared/search-client.ts'

const CONNECTION = { url: 'https://engine.test', key: 'search-only', index: 'posts' }
const SECTION_HEIGHT = 300
const HEADING = 40
const ROW = 60

/** happy-dom does not inherit the stylesheet's custom properties: the panel is given them as written there. */
const TOKENS = [...readFileSync(SEARCH_STYLESHEET, 'utf8').matchAll(/(--meili-(?:duration-[a-z-]+|ease(?:-fade)?)): ([^;]+);/g)]

interface Played {
    node: string
    keyframes: Keyframe[]
    options: KeyframeAnimationOptions
    animation: { onfinish: (() => void) | null }
}

/**
 * happy-dom lays nothing out: sections stack by rank among those shown, cards by rank in their list, and a
 * glide caught mid-way adds what `visual` says to the box, as a running transform would.
 */
const moving = (t: TestContext, { reduced = false, shown = 2000 } = {}) => {
    t.mock.timers.enable({ apis: ['setTimeout'] })

    const engine = new FakeEngine(t)
    const { window, root } = openSearch(undefined, { styled: true })
    const panel = find(root, Contract.selector('search-panel'))
    const input = find<HTMLInputElement>(root, Contract.selector('search-input'))
    const visual = new Map<string, number>()
    const played: Played[] = []
    let cancelled = 0
    let term = 's'

    const named = (node: Element) => [node.getAttribute('data-meili'), node.getAttribute('data-type'), node.matches(Contract.selector('card')) ? node.querySelector(Contract.selector('title'))?.textContent : null]
        .filter((part) => typeof part === 'string')
        .join(':')
    const shownSections = () => [...root.querySelectorAll<HTMLElement>(`${Contract.selector('search-section')}:not([hidden], [data-leaving])`)]
    const topOf = (node: Element): number => {
        const list = node.parentElement

        if (node.matches(Contract.selector('search-section'))) {
            return shownSections().indexOf(node as HTMLElement) * SECTION_HEIGHT
        }

        if (list?.matches(Contract.selector('search-results')) === true) {
            const rank = [...list.children].filter((row) => !row.hasAttribute('data-leaving')).indexOf(node)

            return topOf(list.closest(Contract.selector('search-section')) as Element) + HEADING + rank * ROW
        }

        return 0
    }

    window.HTMLElement.prototype.getBoundingClientRect = function (this: HTMLElement) {
        const top = topOf(this) + (visual.get(named(this)) ?? 0)

        return { top, left: 0, width: 320, height: ROW, right: 320, bottom: top + ROW, x: 0, y: top } as DOMRect
    }
    window.HTMLElement.prototype.getAnimations = () => []
    window.HTMLElement.prototype.animate = function (this: HTMLElement, keyframes: Keyframe[], options: KeyframeAnimationOptions) {
        const animation = { onfinish: null, cancel: () => cancelled++ }

        played.push({ node: named(this), keyframes, options, animation })

        return animation as unknown as Animation
    }
    window.matchMedia = (() => ({ matches: reduced })) as unknown as typeof window.matchMedia
    panel.hidden = false
    Object.defineProperty(panel, 'clientHeight', { value: shown })
    TOKENS.forEach(([, name = '', value = '']) => panel.style.setProperty(name, value))

    new SiteSearch({ contract: new Contract(root), description: searchDescribed(), connection: CONNECTION }).start()

    /** Each call types one more letter: the same term twice would search nothing. */
    const answer = async (products: SearchHit[], posts: SearchHit[] = []) => {
        term += 'e'
        typeInto(window, input, term)
        t.mock.timers.tick(120)
        engine.last.answer([{ hits: products, totalHits: products.length }, { hits: posts, totalHits: posts.length }])
        await settle()
    }

    const leaving = () => [...root.querySelectorAll<HTMLElement>('[data-leaving]')]

    return { window, root, panel, input, played, visual, answer, leaving, named, cancelled: () => cancelled }
}

const cards = (...ids: number[]) => ids.map((id) => hit(id, `Card ${id}`))

describe('ResultsMotion', () => {
    it('fades a new card in from 4 px below, and leaves alone a card found again in its place', async (t) => {
        const { played, answer } = moving(t)

        await answer(cards(1, 2))
        played.length = 0
        await answer(cards(1, 2, 3))

        assert.deepEqual(played.map(({ node, keyframes, options }) => [node, keyframes, options.duration, options.easing]), [
            ['card:Card 3', [{ opacity: 0, transform: 'translateY(4px)' }, { opacity: 1, transform: 'none' }], 120, 'cubic-bezier(0.23, 1, 0.32, 1)'],
            ['search-count', [{ opacity: 0, transform: 'translateY(2px)' }, { opacity: 1, transform: 'none' }], 120, 'cubic-bezier(0.23, 1, 0.32, 1)'],
        ])
    })

    it('glides a card to its new rank from where it stood, added to whatever moves it already', async (t) => {
        const { played, answer } = moving(t)

        await answer(cards(1, 2))
        played.length = 0
        await answer(cards(2, 1))

        assert.deepEqual(played.map(({ node, keyframes, options }) => [node, keyframes[0]?.transform, options.duration, options.composite]), [
            ['card:Card 2', `translate(0px, ${ROW}px)`, 150, 'add'],
            ['card:Card 1', `translate(0px, ${-ROW}px)`, 150, 'add'],
        ])
    })

    it('carries on from where the eye sees a card when an answer overtakes its glide, cancelling nothing', async (t) => {
        const { played, visual, answer, cancelled } = moving(t)

        await answer(cards(1, 2))
        await answer(cards(2, 1))
        visual.set('card:Card 2', 30)
        played.length = 0
        await answer(cards(1, 2))

        const glide = played.find(({ node }) => node === 'card:Card 2')
        const start = Number(/translate\(0px, (-?\d+)px\)/.exec(String(glide?.keyframes[0]?.transform))?.[1])
        const seenBefore = HEADING + 30
        const seenAtStart = HEADING + ROW + 30 + start

        assert.equal(glide?.options.composite, 'add')
        assert.equal(seenAtStart, seenBefore)
        assert.equal(cancelled(), 0)
    })

    it('fades a lost card out off the flow, where it stood, and drops it once faded', async (t) => {
        const { root, played, answer, leaving } = moving(t)

        await answer(cards(1, 2))
        played.length = 0
        await answer(cards(1))

        const [ghost] = leaving()
        const fade = played.find(({ node }) => node === 'card:Card 2')

        assert.deepEqual([ghost?.style.top, ghost?.style.maxHeight, ghost?.getAttribute('aria-hidden'), ghost?.inert], [`${HEADING + ROW}px`, `${2000 - HEADING - ROW}px`, 'true', true])
        assert.deepEqual([fade?.keyframes, fade?.options.duration, fade?.options.easing], [[{ opacity: 1 }, { opacity: 0 }], 80, 'ease'])
        assert.equal(root.querySelectorAll(`${Contract.selector('card')}:not([data-leaving])`).length, 1)

        fade?.animation.onfinish?.()
        assert.equal(leaving().length, 0)
    })

    it('fades a section out as a copy with no id, and brings the next one in without animating its cards', async (t) => {
        const { root, played, answer, leaving } = moving(t)

        await answer(cards(1))
        played.length = 0
        await answer([], cards(2))

        const [copy] = leaving()

        assert.equal(copy?.getAttribute('data-type'), 'product')
        assert.equal(copy?.querySelectorAll('[id]').length, 0)
        assert.equal(find(root, '[data-type="product"]:not([data-leaving])').hidden, true)
        assert.deepEqual(played.map(({ node, keyframes }) => [node, keyframes]), [
            ['search-section:product', [{ opacity: 1 }, { opacity: 0 }]],
            ['search-section:post', [{ opacity: 0, transform: 'none' }, { opacity: 1, transform: 'none' }]],
        ])
    })

    it('crossfades into the empty message and out of it in 150 ms', async (t) => {
        const { played, answer } = moving(t)

        await answer(cards(1))
        played.length = 0
        await answer([])
        const toEmpty = played.map(({ node, options }) => [node, options.duration])

        played.length = 0
        await answer(cards(1))

        assert.deepEqual(toEmpty, [['search-section:product', 150], ['search-empty', 150]])
        assert.deepEqual(played.map(({ node, options }) => [node, options.duration]), [['search-empty', 150], ['search-section:product', 150]])
    })

    it('lets the first results in with the panel when it is still coming in', async (t) => {
        const { panel, played, answer } = moving(t)

        panel.getAnimations = () => [{} as Animation]
        await answer(cards(1), cards(2))

        assert.deepEqual(played, [])
    })

    it('only fades under reduced motion: nothing glides, nothing slides in', async (t) => {
        const { played, answer } = moving(t, { reduced: true })

        await answer(cards(1, 2))
        played.length = 0
        await answer(cards(2, 1, 3))

        assert.deepEqual(played.map(({ node, keyframes }) => [node, keyframes]), [
            ['card:Card 3', [{ opacity: 0 }, { opacity: 1 }]],
            ['search-count', [{ opacity: 0 }, { opacity: 1 }]],
        ])
    })

    it('animates nothing at a key', async (t) => {
        const { window, input, played, answer } = moving(t)

        await answer(cards(1, 2))
        played.length = 0
        press(window, input, 'ArrowDown')
        press(window, input, 'ArrowDown')
        press(window, input, 'ArrowUp')

        assert.deepEqual(played, [])
        assert.notEqual(input.getAttribute('aria-activedescendant'), null)
    })

    it('lets go of the card the arrows reached when an answer loses it, its ghost unmarked', async (t) => {
        const { window, input, answer, leaving } = moving(t)

        await answer(cards(1, 2))
        press(window, input, 'ArrowDown')
        await answer(cards(2))

        assert.equal(input.hasAttribute('aria-activedescendant'), false)
        assert.deepEqual(leaving().map((ghost) => ghost.hasAttribute('data-active')), [false])
    })

    it('lets a card that stood past what the panel shows go at once: its ghost would stretch the scroll', async (t) => {
        const { played, answer, leaving } = moving(t, { shown: HEADING + ROW })

        await answer(cards(1, 2))
        played.length = 0
        await answer(cards(1))

        assert.deepEqual(leaving(), [])
        assert.deepEqual(played.map(({ node }) => node), ['search-count'])
    })
})
