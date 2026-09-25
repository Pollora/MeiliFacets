import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { PanelMotion } from '../../resources/assets/ts/collapsible/panel-motion.ts'
import { find, load } from './dom.ts'

import type { TestWindow } from './dom.ts'

describe('PanelMotion', () => {
    let window: TestWindow
    let panel: HTMLElement
    let played: { keyframes: Keyframe[], options: KeyframeAnimationOptions }[]
    let reduced: boolean

    const tall = (height: number) => Object.defineProperty(panel, 'offsetHeight', { value: height, configurable: true })

    beforeEach(() => {
        window = load('<div id="panel" hidden></div>')
        panel = find(window.document, '#panel')
        played = []
        reduced = false
        panel.animate = ((keyframes: Keyframe[], options: KeyframeAnimationOptions) => {
            played.push({ keyframes, options })

            return {} as Animation
        })
        window.matchMedia = (() => ({ matches: reduced })) as unknown as typeof window.matchMedia
    })

    /** Family drawer's rule: |Δh| / 500 s, between 150 and 270 ms. */
    it('shows a panel over a time that grows with its height, within bounds', () => {
        const motion = new PanelMotion(window.document)

        for (const height of [20, 100, 400]) {
            tall(height)
            motion.show(panel)
        }

        assert.deepEqual(played.map(({ options }) => options.duration), [150, 200, 270])
        assert.equal(panel.hidden, false)
    })

    it('fades and slightly scales in, and only fades under reduced motion', () => {
        tall(100)
        new PanelMotion(window.document).show(panel)
        reduced = true
        new PanelMotion(window.document).show(panel)

        assert.deepEqual(played[0]?.keyframes, [{ opacity: 0, transform: 'scale(0.96)' }, { opacity: 1, transform: 'none' }])
        assert.deepEqual(played[1]?.keyframes, [{ opacity: 0 }, { opacity: 1 }])
    })

    it('times the way out shorter, before the panel goes', () => {
        panel.hidden = false
        tall(100)

        void new PanelMotion(window.document).hide(panel)

        assert.equal(panel.style.getPropertyValue('--meili-duration-content'), '150ms')
        assert.equal(panel.hidden, true)
    })

    it('ends a running entry before the exit starts', () => {
        let finished = 0
        panel.hidden = false
        panel.getAnimations = () => [{ finish: () => finished++ } as unknown as Animation]

        void new PanelMotion(window.document).hide(panel)

        assert.equal(finished, 1)
    })

    it('settles once the panel is out, and not before', async () => {
        let out = () => {}
        const finished = new Promise<void>((resolve) => {
            out = resolve
        })
        let settled = false
        panel.hidden = false
        panel.getAnimations = () => [{ finish: () => {}, finished } as unknown as Animation]

        void new PanelMotion(window.document).hide(panel).then(() => {
            settled = true
        })
        await Promise.resolve()
        assert.equal(settled, false)

        out()
        await new Promise((resolve) => setTimeout(resolve, 0))
        assert.equal(settled, true)
    })

    it('drops a panel already out of sight without its exit', () => {
        let finished = 0
        panel.hidden = false
        panel.getAnimations = () => [{ finish: () => finished++ } as unknown as Animation]

        new PanelMotion(window.document).drop(panel)

        assert.equal(panel.hidden, true)
        assert.equal(finished, 1)
        assert.equal(panel.style.getPropertyValue('--meili-duration-content'), '')
    })

    /** ANIM-3: floating, a panel moves nothing around it — the stylesheet's fixed time, whatever its height. */
    it('pops a floating panel in over the time the stylesheet gives, in milliseconds or seconds', () => {
        const motion = new PanelMotion(window.document)
        tall(400)

        for (const declared of ['220ms', '0.2s', '']) {
            panel.style.setProperty('--meili-duration-panel-in', declared)
            motion.pop(panel)
        }

        assert.deepEqual(played.map(({ options }) => options.duration), [220, 200, 180])
        assert.equal(panel.hidden, false)
    })
})
