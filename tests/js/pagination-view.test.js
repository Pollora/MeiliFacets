import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { Contract } from '../../resources/assets/js/contract.js'
import { ListingState } from '../../resources/assets/js/listing-state.js'
import { PaginationView } from '../../resources/assets/js/pagination-view.js'
import { listingMarkup, open } from './dom.js'

const UNBOUNDED = 1_000_000

const description = { perPage: 10, reachableHits: UNBOUNDED }

describe('PaginationView', () => {
    let root
    let view

    beforeEach(() => {
        root = open(listingMarkup()).root
        view = new PaginationView(new Contract(root), description)
    })

    const nav = () => root.querySelector(Contract.selector('pagination'))
    const numbers = () => [...root.querySelectorAll(Contract.selector('page'))]
    const shown = () => numbers().filter((button) => !button.hidden).map((button) => button.textContent)
    const step = (hook) => root.querySelector(Contract.selector(hook))

    it('stays out of the way when everything fits on one page', () => {
        view = new PaginationView(new Contract(root), { perPage: 16, reachableHits: UNBOUNDED })
        view.show(new ListingState({ page: 1 }), 12)

        assert.equal(nav().hidden, true)
    })

    it('reveals one button per page and leaves the rest empty', () => {
        view.show(new ListingState({ page: 1 }), 25)

        assert.deepEqual(shown(), ['1', '2', '3'])
        assert.equal(numbers()[3].value, '')
        assert.equal(nav().hidden, false)
    })

    it('marks the page being read', () => {
        view.show(new ListingState({ page: 2 }), 25)

        assert.equal(numbers()[1].getAttribute('aria-current'), 'page')
        assert.equal(numbers()[0].hasAttribute('aria-current'), false)
    })

    it('moves the mark rather than leaving two', () => {
        view.show(new ListingState({ page: 2 }), 25)
        view.show(new ListingState({ page: 3 }), 25)

        assert.deepEqual(numbers().filter((button) => button.hasAttribute('aria-current')).length, 1)
    })

    it('carries the page each step leads to', () => {
        view.show(new ListingState({ page: 2 }), 95)

        assert.equal(step('previous').value, '1')
        assert.equal(step('previous').hidden, false)
        assert.equal(step('next').value, '3')
        assert.equal(step('next').hidden, false)
    })

    it('hides the step there is nowhere to take', () => {
        view.show(new ListingState({ page: 1 }), 25)

        assert.equal(step('previous').hidden, true)
        assert.equal(step('next').hidden, false)
    })

    /** Hiding the next step under the finger that pressed it drops the focus on the body. */
    it('keeps the focus inside the pagination when the step it used disappears', () => {
        view.show(new ListingState({ page: 2 }), 25)
        step('next').focus()

        view.show(new ListingState({ page: 3 }), 25)

        assert.equal(step('next').hidden, true)
        assert.equal(root.ownerDocument.activeElement, numbers()[2])
    })

    /** Focusing a button scrolls it into view, which would undo the move to the top. */
    it('moves the focus without dragging the page after it', () => {
        view.show(new ListingState({ page: 2 }), 25)
        step('next').focus()

        const asked = []

        numbers()[2].focus = (options) => asked.push(options)
        view.show(new ListingState({ page: 3 }), 25)

        assert.deepEqual(asked, [{ preventScroll: true }])
    })

    it('leaves the focus alone while the step it used stays', () => {
        view.show(new ListingState({ page: 1 }), 25)
        step('next').focus()

        view.show(new ListingState({ page: 2 }), 25)

        assert.equal(root.ownerDocument.activeElement, step('next'))
    })

    /** A theme rendering five buttons must centre on five, not truncate a window of seven. */
    it('fits the window to the buttons the theme rendered', () => {
        root.querySelectorAll(Contract.selector('page')).forEach((button, rank) => {
            if (rank >= 5) {
                button.remove()
            }
        })

        view.show(new ListingState({ page: 50 }), 1000)

        assert.deepEqual(shown(), ['48', '49', '50', '51', '52'])
    })

    /** 1570 hits, ten a page, an engine serving 1000: a hundred pages, not 157. */
    it('offers no page past what the engine serves', () => {
        view = new PaginationView(new Contract(root), { perPage: 10, reachableHits: 1000 })
        view.show(new ListingState({ page: 100 }), 1570)

        assert.deepEqual(shown(), ['94', '95', '96', '97', '98', '99', '100'])
        assert.equal(step('next').hidden, true)
    })
})
