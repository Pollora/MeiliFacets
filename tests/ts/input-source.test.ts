import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { InputSource } from '../../resources/assets/ts/shared/input-source.ts'
import { click, find, load, press } from './dom.ts'

import type { TestWindow } from './dom.ts'

describe('InputSource', () => {
    let window: TestWindow
    let button: HTMLElement
    let input: InputSource

    const pointerDown = () => button.dispatchEvent(new window.PointerEvent('pointerdown', { bubbles: true }))

    beforeEach(() => {
        window = load('<button id="button" type="button">Open</button>')
        button = find(window.document, '#button')
        input = new InputSource(window.document).start()
    })

    it('tells a click the keyboard raised from a real one', () => {
        const heard: boolean[] = []

        button.addEventListener('click', (event) => heard.push(InputSource.isKeyboard(event as MouseEvent)))
        click(window, button, 0)
        click(window, button)

        assert.deepEqual(heard, [true, false])
    })

    it('holds a pointer down from its press until its click', () => {
        assert.equal(input.isPointerDown(), false)

        pointerDown()
        assert.equal(input.isPointerDown(), true)

        click(window, button)
        assert.equal(input.isPointerDown(), false)
    })

    it('hands the interaction to a key pressed after the pointer', () => {
        pointerDown()
        press(window, button, 'Tab')

        assert.equal(input.isPointerDown(), false)
    })

    it('knows of the press before any listener on the page, even one that stops it', () => {
        const heard: boolean[] = []

        button.addEventListener('pointerdown', (event) => {
            heard.push(input.isPointerDown())
            event.stopPropagation()
        })
        pointerDown()

        assert.deepEqual(heard, [true])
    })
})
