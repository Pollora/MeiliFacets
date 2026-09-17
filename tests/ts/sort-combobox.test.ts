import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { SortCombobox } from '../../resources/assets/ts/sort/sort-combobox.ts'
import { click, find, listingMarkup, nth, open, press } from './dom.ts'

import type { TestWindow } from './dom.ts'

describe('SortCombobox', () => {
    let window: TestWindow
    let root: Element
    let picked: (string | null)[]
    let combobox: SortCombobox

    beforeEach(() => {
        ({ window, root } = open(listingMarkup()))
        picked = []
        combobox = new SortCombobox(new Contract(root), (sort) => picked.push(sort)).start()
    })

    const trigger = () => find(root, Contract.selector('sort-trigger'))
    const list = () => find(root, Contract.selector('sort-list'))
    const option = (rank: number) => nth(root, Contract.selector('sort-option'), rank)
    const active = () => trigger().getAttribute('aria-activedescendant')

    it('opens on the option that is already chosen', () => {
        combobox.show(new ListingState({ sort: 'newest' }))
        click(window, trigger())

        assert.equal(list().hidden, false)
        assert.equal(trigger().getAttribute('aria-expanded'), 'true')
        assert.equal(active(), 'sort-newest')
    })

    it('closes on a second press', () => {
        click(window, trigger())
        click(window, trigger())

        assert.equal(list().hidden, true)
        assert.equal(trigger().hasAttribute('aria-activedescendant'), false)
    })

    it('walks the options with the arrows, without moving the focus', () => {
        press(window, trigger(), 'ArrowDown')
        press(window, trigger(), 'ArrowDown')

        assert.equal(active(), 'sort-price_asc')
        assert.equal(option(1).hasAttribute('data-active'), true)

        press(window, trigger(), 'ArrowUp')

        assert.equal(active(), 'sort-default')
    })

    it('stops at both ends of the list', () => {
        press(window, trigger(), 'End')
        press(window, trigger(), 'ArrowDown')

        assert.equal(active(), 'sort-newest')

        press(window, trigger(), 'Home')
        press(window, trigger(), 'ArrowUp')

        assert.equal(active(), 'sort-default')
    })

    it('hands over the order that was picked', () => {
        press(window, trigger(), 'ArrowDown')
        press(window, trigger(), 'ArrowDown')
        press(window, trigger(), 'Enter')

        assert.deepEqual(picked, ['price_asc'])
        assert.equal(list().hidden, true)
    })

    /** The engine's own order is the absence of a sort, not a sort named "". */
    it('hands over nothing at all for relevance', () => {
        combobox.show(new ListingState({ sort: 'newest' }))
        press(window, trigger(), 'Home')
        press(window, trigger(), 'Enter')

        assert.deepEqual(picked, [null])
    })

    it('picks the option that was clicked', () => {
        click(window, trigger())
        click(window, option(2))

        assert.deepEqual(picked, ['newest'])
    })

    it('leaves the choice alone on escape', () => {
        press(window, trigger(), 'ArrowDown')
        press(window, trigger(), 'ArrowDown')
        press(window, trigger(), 'Escape')

        assert.deepEqual(picked, [])
        assert.equal(list().hidden, true)
    })

    it('closes when the visitor presses elsewhere', () => {
        click(window, trigger())
        click(window, window.document.body)

        assert.equal(list().hidden, true)
    })

    it('opens on the first option a letter matches', () => {
        press(window, trigger(), 'n')

        assert.equal(list().hidden, false)
        assert.equal(active(), 'sort-newest')
    })

    it('follows a name being spelled out rather than restarting at every letter', () => {
        press(window, trigger(), 'p')
        press(window, trigger(), 'r')
        press(window, trigger(), 'i')

        assert.equal(active(), 'sort-price_asc')
    })

    it('shows the order the state holds', () => {
        combobox.show(new ListingState({ sort: 'price_asc' }))

        assert.equal(trigger().textContent, 'Price, low to high')
        assert.equal(option(1).getAttribute('aria-selected'), 'true')
        assert.equal(option(0).getAttribute('aria-selected'), 'false')
    })

    it('goes back to relevance when the state carries no order', () => {
        combobox.show(new ListingState({ sort: 'price_asc' }))
        combobox.show(new ListingState())

        assert.equal(trigger().textContent, 'Relevance')
        assert.equal(option(0).getAttribute('aria-selected'), 'true')
    })
})
