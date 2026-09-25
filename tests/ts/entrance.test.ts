import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { Entrance } from '../../resources/assets/ts/shared/entrance.ts'
import { find, load } from './dom.ts'

import type { TestWindow } from './dom.ts'

const badge = { from: 'scale(0.9)', duration: '--meili-duration-fade', easing: '--meili-ease' }

describe('Entrance', () => {
    let window: TestWindow
    let counter: HTMLElement
    let played: { keyframes: Keyframe[], options: KeyframeAnimationOptions }[]
    let reduced: boolean

    beforeEach(() => {
        window = load('<span id="counter" style="--meili-duration-fade: 150ms; --meili-ease: ease-in-out">1</span>')
        counter = find(window.document, '#counter')
        played = []
        reduced = false
        counter.animate = ((keyframes: Keyframe[], options: KeyframeAnimationOptions) => {
            played.push({ keyframes, options })

            return {} as Animation
        })
        window.matchMedia = (() => ({ matches: reduced })) as unknown as typeof window.matchMedia
    })

    it('comes in at the stylesheet\'s pace', () => {
        new Entrance(window.document, badge).play(counter)

        assert.deepEqual(played[0]?.options, { duration: 150, easing: 'ease-in-out' })
    })

    it('reads a length the theme wrote in seconds', () => {
        counter.style.setProperty('--meili-duration-fade', '0.15s')

        new Entrance(window.document, badge).play(counter)

        assert.equal(played[0]?.options.duration, 150)
    })

    it('scales in from where its owner asks, never from nothing', () => {
        new Entrance(window.document, { ...badge, from: 'scale(0.95)' }).play(counter)

        assert.deepEqual(played[0]?.keyframes, [{ opacity: 0, transform: 'scale(0.95)' }, { opacity: 1, transform: 'none' }])
    })

    it('takes a length measured at the time over the stylesheet\'s', () => {
        new Entrance(window.document, badge).play(counter, 240)

        assert.equal(played[0]?.options.duration, 240)
    })

    it('only fades in under reduced motion', () => {
        reduced = true

        new Entrance(window.document, badge).play(counter)

        assert.deepEqual(played[0]?.keyframes, [{ opacity: 0 }, { opacity: 1 }])
    })
})
