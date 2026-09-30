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
const FIELD = 100
const EASE = 'cubic-bezier(0.23, 1, 0.32, 1)'

/** happy-dom neither inherits the stylesheet's custom properties nor substitutes `var()`: the panel is given them resolved. */
const DECLARED = new Map([...readFileSync(SEARCH_STYLESHEET, 'utf8').matchAll(/(--meili-(?:duration|ease)[a-z-]*): ([^;]+);/g)].map(([, name = '', value = '']) => [name, value]))
const TOKENS = [...DECLARED].map(([name, value]) => [name, value.replace(/^var\((--[a-z-]+)\)$/, (reference, token: string) => DECLARED.get(token) ?? reference)])

interface Played {
    node: string
    keyframes: Keyframe[]
    options: KeyframeAnimationOptions
    animation: { onfinish: (() => void) | null }
}

const clipped = (height: number) => ({ overflowY: 'hidden', height: `${height}px` })

/**
 * happy-dom lays nothing out: sections stack by rank among those shown, cards by rank in their list, and a
 * glide caught mid-way adds what `visual` says to the box, as a running transform would. A panel that follows its
 * content is as tall as its field and its cards, unless a resize under way holds it at `resized.height`.
 */
const moving = (t: TestContext, { reduced = false, shown = 2000, followsContent = false } = {}) => {
    t.mock.timers.enable({ apis: ['setTimeout'] })

    const engine = new FakeEngine(t)
    const { window, root } = openSearch(undefined, { styled: true })
    const panel = find(root, Contract.selector('search-panel'))
    const input = find<HTMLInputElement>(root, Contract.selector('search-input'))
    const visual = new Map<string, number>()
    const played: Played[] = []
    const cancelled: string[] = []
    const resized: { height: number | null } = { height: null }
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
        const node = named(this)
        const animation = { id: options.id ?? '', onfinish: null, cancel: () => {
            cancelled.push(node)
            resized.height = node === 'search-panel' ? null : resized.height
        } }

        played.push({ node, keyframes, options, animation })

        return animation as unknown as Animation
    }
    window.matchMedia = (() => ({ matches: reduced })) as unknown as typeof window.matchMedia
    panel.hidden = false
    Object.defineProperty(panel, 'clientHeight', { value: shown })

    if (followsContent) {
        const cardsShown = () => root.querySelectorAll(`${Contract.selector('card')}:not([data-leaving])`).length

        panel.getBoundingClientRect = () => ({ top: 0, left: 0, height: resized.height ?? FIELD + cardsShown() * ROW }) as DOMRect
    }
    TOKENS.forEach(([name = '', value = '']) => panel.style.setProperty(name, value))

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

    return { window, root, panel, input, played, visual, resized, answer, leaving, named, cancelled }
}

const cards = (...ids: number[]) => ids.map((id) => hit(id, `Card ${id}`))

describe('ResultsMotion', () => {
    it('fades a new card in from 4 px below, and leaves alone a card found again in its place', async (t) => {
        const { played, answer } = moving(t)

        await answer(cards(1, 2))
        played.length = 0
        await answer(cards(1, 2, 3))

        assert.deepEqual(played.map(({ node, keyframes, options }) => [node, keyframes, options.duration, options.easing]), [
            ['card:Card 3', [{ opacity: 0, transform: 'translateY(4px)' }, { opacity: 1, transform: 'none' }], 120, EASE],
            ['search-count', [{ opacity: 0, transform: 'translateY(2px)' }, { opacity: 1, transform: 'none' }], 120, EASE],
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
        assert.deepEqual(cancelled, [])
    })

    it('fades a lost card out off the flow, where it stood, drifting up 4 px, and drops it once faded', async (t) => {
        const { root, played, answer, leaving } = moving(t)

        await answer(cards(1, 2))
        played.length = 0
        await answer(cards(1))

        const [ghost] = leaving()
        const fade = played.find(({ node }) => node === 'card:Card 2')

        assert.deepEqual([ghost?.style.top, ghost?.style.maxHeight, ghost?.getAttribute('aria-hidden'), ghost?.inert], [`${HEADING + ROW}px`, `${2000 - HEADING - ROW}px`, 'true', true])
        assert.deepEqual([fade?.keyframes, fade?.options.duration, fade?.options.easing], [
            [{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(-4px)' }], 120, 'ease',
        ])
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
            ['search-section:product', [{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(-4px)' }]],
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

        panel.getAnimations = () => [{ transitionProperty: 'opacity' } as unknown as Animation]
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

        assert.equal(leaving().length, 0)
        assert.deepEqual(played.map(({ node }) => node), ['search-count'])
    })

    it('eases the height of a panel that follows its content from the old height to the new, in 200 ms', async (t) => {
        const { played, answer } = moving(t, { followsContent: true })

        await answer(cards(1, 2, 3, 4))
        played.length = 0
        await answer(cards(1))

        const resize = played.find(({ node }) => node === 'search-panel')

        assert.deepEqual([resize?.keyframes, resize?.options.duration, resize?.options.easing], [
            [clipped(FIELD + 4 * ROW), clipped(FIELD + ROW)], 200, EASE,
        ])
    })

    it('keeps in sight the cards the panel still shows while its edge eases up', async (t) => {
        const { answer, leaving } = moving(t, { followsContent: true, shown: HEADING + ROW })

        await answer(cards(1, 2, 3, 4))
        await answer(cards(1))

        assert.equal(leaving().length, 3)
    })

    it('leaves the height of a panel given the full room alone', async (t) => {
        const { played, answer } = moving(t)

        await answer(cards(1, 2, 3, 4))
        played.length = 0
        await answer(cards(1))

        assert.equal(played.some(({ node }) => node === 'search-panel'), false)
    })

    it('changes the height at once under reduced motion, so nothing past the new edge stays to fade', async (t) => {
        const { played, answer, leaving } = moving(t, { followsContent: true, reduced: true, shown: HEADING + ROW })

        await answer(cards(1, 2))
        played.length = 0
        await answer(cards(1))

        assert.deepEqual(played.map(({ node, keyframes }) => [node, keyframes]), [['search-count', [{ opacity: 0 }, { opacity: 1 }]]])
        assert.equal(leaving().length, 0)
    })

    it('fades a card out without drifting under reduced motion', async (t) => {
        const { played, answer } = moving(t, { reduced: true })

        await answer(cards(1, 2))
        played.length = 0
        await answer(cards(1))

        assert.deepEqual(played.find(({ node }) => node === 'card:Card 2')?.keyframes, [{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'none' }])
    })

    it('animates the results and the height under a transition of the panel that neither brings it in nor takes it out', async (t) => {
        const { panel, played, answer } = moving(t, { followsContent: true })

        panel.getAnimations = () => [{ transitionProperty: 'background-color' } as unknown as Animation]
        await answer(cards(1, 2, 3, 4))
        played.length = 0
        await answer(cards(1, 5))

        assert.deepEqual(played.map(({ node }) => node), ['card:Card 2', 'card:Card 3', 'card:Card 4', 'card:Card 5', 'search-count', 'search-panel'])
    })

    it('eases the height on the curve the theme gives it', async (t) => {
        const { panel, played, answer } = moving(t, { followsContent: true })

        panel.style.setProperty('--meili-ease-resize', 'linear')
        await answer(cards(1, 2, 3, 4))
        played.length = 0
        await answer(cards(1))

        assert.equal(played.find(({ node }) => node === 'search-panel')?.options.easing, 'linear')
    })

    it('leaves the height to the panel while it comes in', async (t) => {
        const { panel, played, answer } = moving(t, { followsContent: true })

        panel.getAnimations = () => [{ transitionProperty: 'opacity' } as unknown as Animation]
        await answer(cards(1, 2, 3, 4))
        await answer(cards(1))

        assert.equal(played.some(({ node }) => node === 'search-panel'), false)
    })

    it('starts a resize an answer overtakes from the height the eye sees, and still lets new cards in', async (t) => {
        const { panel, played, resized, answer, cancelled } = moving(t, { followsContent: true })

        await answer(cards(1, 2, 3, 4))
        await answer(cards(1))

        const running = played.findLast(({ node }) => node === 'search-panel')?.animation as unknown as Animation

        panel.getAnimations = () => [running]
        resized.height = FIELD + 2.5 * ROW
        played.length = 0
        cancelled.length = 0
        await answer(cards(1, 2))

        assert.deepEqual(cancelled, ['search-panel'])
        assert.deepEqual(played.map(({ node, keyframes }) => [node, node === 'search-panel' ? keyframes : null]), [
            ['card:Card 2', null],
            ['search-count', null],
            ['search-panel', [clipped(FIELD + 2.5 * ROW), clipped(FIELD + 2 * ROW)]],
        ])
    })
})
