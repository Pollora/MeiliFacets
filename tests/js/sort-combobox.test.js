import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { Contract } from '../../resources/assets/js/contract.js'
import { ListingState } from '../../resources/assets/js/listing-state.js'
import { SortCombobox } from '../../resources/assets/js/sort-combobox.js'
import { click, listingMarkup, open, press } from './dom.js'

describe('SortCombobox', () => {
    let window
    let root
    let picked
    let combobox

    beforeEach(() => {
        ({ window, root } = open(listingMarkup()))
        picked = []
        combobox = new SortCombobox(new Contract(root), (sort) => picked.push(sort)).start()
    })

    const trigger = () => root.querySelector(Contract.selector('sort-trigger'))
    const list = () => root.querySelector(Contract.selector('sort-list'))
    const options = () => [...root.querySelectorAll(Contract.selector('sort-option'))]
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
        assert.equal(options()[1].hasAttribute('data-active'), true)

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
        click(window, options()[2])

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
        assert.equal(options()[1].getAttribute('aria-selected'), 'true')
        assert.equal(options()[0].getAttribute('aria-selected'), 'false')
    })

    it('goes back to relevance when the state carries no order', () => {
        combobox.show(new ListingState({ sort: 'price_asc' }))
        combobox.show(new ListingState())

        assert.equal(trigger().textContent, 'Relevance')
        assert.equal(options()[0].getAttribute('aria-selected'), 'true')
    })
})
