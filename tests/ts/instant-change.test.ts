import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { INSTANT } from '../../resources/assets/ts/shared/attributes.ts'
import { InstantChange } from '../../resources/assets/ts/shared/instant-change.ts'
import { find, load } from './dom.ts'

describe('InstantChange', () => {
    it('cuts the transitions around the change, flushes the style before it lets them back', () => {
        const panel = find(load('<div id="panel"></div>').document, '#panel')
        const steps: string[] = []
        panel.getAnimations = () => {
            steps.push(panel.hasAttribute(INSTANT) ? 'flushed while cut' : 'flushed after')

            return []
        }

        new InstantChange(panel).apply(() => steps.push(panel.hasAttribute(INSTANT) ? 'changed while cut' : 'changed after'))

        assert.deepEqual(steps, ['changed while cut', 'flushed while cut'])
        assert.equal(panel.hasAttribute(INSTANT), false)
    })

    it('lets the transitions back when the change throws', () => {
        const panel = find(load('<div id="panel"></div>').document, '#panel')

        assert.throws(() => new InstantChange(panel).apply(() => {
            throw new Error('broken')
        }))
        assert.equal(panel.hasAttribute(INSTANT), false)
    })
})
