import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { ComboboxKeys } from '../../resources/assets/ts/site-search/combobox-keys.ts'
import { find, press } from './dom.ts'
import { openSearch } from './site-search-fixtures.ts'

const combobox = (count: number) => {
    const { window, root } = openSearch()
    const input = find<HTMLInputElement>(root, Contract.selector('search-input'))
    const list = find(root, Contract.selector('search-results'))

    list.innerHTML = Array.from({ length: count }, (_, rank) =>
        `<li data-meili="card"><a href="https://example.test/${rank}" data-meili="url">Option ${rank}</a></li>`).join('')

    const options = [...list.querySelectorAll<HTMLElement>(Contract.selector('card'))]
    const followed: string[] = []

    list.addEventListener('click', (event) => {
        followed.push((event.target as HTMLAnchorElement).href)
        event.preventDefault()
    })

    const keys = new ComboboxKeys(new Contract(root), input).start()

    keys.offer(options)

    const pressed = (key: string) => {
        const event = new window.KeyboardEvent('keydown', { key, bubbles: true, cancelable: true })

        input.dispatchEvent(event)

        return event.defaultPrevented
    }

    const active = () => options.findIndex((option) => option.id === input.getAttribute('aria-activedescendant'))
    const marked = () => options.map((option) => option.hasAttribute('data-active'))

    return { window, input, keys, options, followed, pressed, active, marked }
}

describe('ComboboxKeys', () => {
    it('gives each option an id and opens the combobox while it offers some', () => {
        const { input, options, keys } = combobox(2)

        assert.deepEqual(options.map((option) => option.id), ['search-input-option-0', 'search-input-option-1'])
        assert.equal(input.getAttribute('aria-expanded'), 'true')

        keys.offer([])
        assert.equal(input.getAttribute('aria-expanded'), 'false')
    })

    it('moves with the arrows, from the field to the first option or up to the last, and stops at the ends', () => {
        const { pressed, active, marked } = combobox(3)

        assert.equal(pressed('ArrowDown'), true)
        assert.equal(active(), 0)
        pressed('ArrowDown')
        pressed('ArrowDown')
        pressed('ArrowDown')
        assert.equal(active(), 2)
        assert.deepEqual(marked(), [false, false, true])
        pressed('ArrowUp')
        assert.equal(active(), 1)
    })

    it('reaches the last option from the field with the up arrow', () => {
        const { pressed, active } = combobox(3)

        pressed('ArrowUp')

        assert.equal(active(), 2)
    })

    it('follows the link of the option the arrows reached on Enter', () => {
        const { pressed, followed } = combobox(3)

        pressed('ArrowDown')
        pressed('ArrowDown')

        assert.equal(pressed('Enter'), true)
        assert.deepEqual(followed, ['https://example.test/1'])
    })

    it('does nothing on Enter while no option is reached (S-9)', () => {
        const { pressed, followed, active } = combobox(3)

        assert.equal(pressed('Enter'), true)
        assert.deepEqual(followed, [])
        assert.equal(active(), -1)
    })

    it('leaves Home, End and the side arrows to the text, the option given back', () => {
        const { input, pressed, active, marked } = combobox(3)

        for (const key of ['Home', 'End', 'ArrowLeft', 'ArrowRight']) {
            pressed('ArrowDown')

            assert.equal(pressed(key), false, key)
            assert.equal(active(), -1, key)
            assert.equal(input.hasAttribute('aria-activedescendant'), false, key)
        }

        assert.deepEqual(marked(), [false, false, false])
    })

    it('leaves the arrows to the field while it offers nothing', () => {
        const { keys, pressed } = combobox(3)

        keys.offer([])

        assert.equal(pressed('ArrowDown'), false)
        assert.equal(pressed('Enter'), true)
    })

    it('forgets the option reached when new options arrive', () => {
        const { keys, options, pressed, active, input } = combobox(3)

        pressed('ArrowDown')
        keys.offer(options.slice(1))

        assert.equal(active(), -1)
        assert.equal(input.hasAttribute('aria-activedescendant'), false)
    })

    it('lets an input method confirm its composition', () => {
        const { window, input, followed } = combobox(1)
        const event = new window.KeyboardEvent('keydown', { key: 'ArrowDown', isComposing: true, bubbles: true, cancelable: true })

        input.dispatchEvent(event)
        press(window, input, 'Enter')

        assert.equal(event.defaultPrevented, false)
        assert.deepEqual(followed, [])
    })

    it('keeps the option reached while it is still offered, at its new rank', () => {
        const { keys, options, pressed, active, input } = combobox(3)
        const [first, second, third] = options

        pressed('ArrowDown')
        pressed('ArrowDown')
        keys.offer([third, second, first].filter((option) => option !== undefined))

        assert.equal(input.getAttribute('aria-activedescendant'), second?.id)
        assert.equal(second?.hasAttribute('data-active'), true)
        pressed('ArrowDown')
        assert.equal(input.getAttribute('aria-activedescendant'), first?.id)
        assert.equal(active(), 0)
    })

    it('lets go of the option reached once it is no longer offered, its mark with it', () => {
        const { keys, options, pressed, input } = combobox(3)
        const [first, second] = options

        pressed('ArrowDown')
        keys.offer([second].filter((option) => option !== undefined))

        assert.equal(input.hasAttribute('aria-activedescendant'), false)
        assert.equal(first?.hasAttribute('data-active'), false)
        assert.equal(pressed('ArrowDown'), true)
        assert.equal(input.getAttribute('aria-activedescendant'), second?.id)
    })

    it('never gives a new option the id a kept one holds', () => {
        const { window, keys, options } = combobox(2)
        const fresh = window.document.createElement('li')

        keys.offer([fresh, ...options.slice(1)] as HTMLElement[])

        assert.equal(new Set([fresh.id, ...options.map((option) => option.id)]).size, 3)
    })
})
