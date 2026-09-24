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

describe('SortCombobox with a sort that filters', () => {
    let window: TestWindow
    let root: Element
    let picked: (string | null)[]
    let combobox: SortCombobox

    beforeEach(() => {
        ({ window, root } = open(listingMarkup()))
        find(root, Contract.selector('sort-list')).insertAdjacentHTML('beforeend', `
            <li id="sort-on_sale" role="option" data-value="on_sale" aria-selected="false" data-meili="sort-option">On sale</li>`)
        picked = []
        combobox = new SortCombobox(new Contract(root), (sort) => picked.push(sort)).start()
    })

    const trigger = () => find(root, Contract.selector('sort-trigger'))
    const promotions = () => find(root, '[data-value="on_sale"]')
    const active = () => trigger().getAttribute('aria-activedescendant')

    it('hides it when it would keep nothing', () => {
        combobox.showMatches({ on_sale: 0 }, new ListingState())

        assert.equal(promotions().hidden, true)
    })

    it('shows it when it would keep something', () => {
        combobox.showMatches({ on_sale: 3 }, new ListingState())

        assert.equal(promotions().hidden, false)
    })

    it('never hides it while it is the sort in use', () => {
        combobox.showMatches({ on_sale: 0 }, new ListingState({ sort: 'on_sale' }))

        assert.equal(promotions().hidden, false)
    })

    it('shows it again once the state picks it, as a way back through history does', () => {
        combobox.showMatches({ on_sale: 0 }, new ListingState())
        combobox.show(new ListingState({ sort: 'on_sale' }))

        assert.equal(promotions().hidden, false)
    })

    it('never lands on it from the keyboard while it is hidden', () => {
        combobox.showMatches({ on_sale: 0 }, new ListingState())

        press(window, trigger(), 'End')
        assert.equal(active(), 'sort-newest')

        press(window, trigger(), 'ArrowDown')
        assert.equal(active(), 'sort-newest')

        press(window, trigger(), 'Escape')
        press(window, trigger(), 'o')
        assert.notEqual(active(), 'sort-on_sale')
    })

    it('reaches it from the keyboard once it shows', () => {
        combobox.showMatches({ on_sale: 3 }, new ListingState())

        press(window, trigger(), 'End')

        assert.equal(active(), 'sort-on_sale')
    })

    it('moves the keyboard back to the sort in use when an answer hides the option it was on', () => {
        combobox.show(new ListingState({ sort: 'price_asc' }))
        combobox.showMatches({ on_sale: 3 }, new ListingState({ sort: 'price_asc' }))
        press(window, trigger(), 'End')

        combobox.showMatches({ on_sale: 0 }, new ListingState({ sort: 'price_asc' }))
        press(window, trigger(), 'Tab')

        assert.deepEqual(picked, ['price_asc'])
    })

    it('ignores a click on it while it is hidden', () => {
        combobox.showMatches({ on_sale: 0 }, new ListingState())
        click(window, trigger())

        click(window, promotions())

        assert.deepEqual(picked, [])
    })
})
