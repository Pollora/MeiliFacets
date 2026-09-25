import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { CountEntry } from '../../resources/assets/ts/listing/count-entry.ts'
import { find, load } from './dom.ts'

import type { TestWindow } from './dom.ts'

describe('CountEntry', () => {
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
        new CountEntry(window.document).play(counter)

        assert.deepEqual(played[0]?.options, { duration: 150, easing: 'ease-in-out' })
    })

    it('only fades in under reduced motion', () => {
        reduced = true

        new CountEntry(window.document).play(counter)

        assert.deepEqual(played[0]?.keyframes, [{ opacity: 0 }, { opacity: 1 }])
    })
})
