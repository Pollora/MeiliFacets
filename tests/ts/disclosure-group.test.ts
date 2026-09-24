import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { DisclosureGroup } from '../../resources/assets/ts/collapsible/disclosure-group.ts'
import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { click, find, listingMarkup, nth, open, press, tick } from './dom.ts'
import { connection, described, FakeClient, FakeHistory } from './fixtures.ts'

import type { TestWindow } from './dom.ts'

const description = described({
    name: 'products',
    perPage: 10,
    reachableHits: 1000,
    filter: 'post_type = "product"',
    apply: 'immediate',
    attributes: ['card'],
    countPattern: ':count result|:count results',
    filterPattern: ':count active filter|:count active filters',
    totalPattern: ':count item|:count items',
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 5, visible: 10, labels: {}, counts: { acme: 3, globex: 2 } },
        { taxonomy: 'product_cat', multiple: false, cap: 1, visible: 10, labels: {}, counts: { coats: 1 } },
    ],
    params: { product_brand: 'brand', product_cat: 'categorie' },
    reserved: { sort: 'sort', query: 'q', page: 'pg', minPrice: 'min_price', maxPrice: 'max_price' },
    sorts: {},
})

/** A macrotask: the fake engine answers on a resolved promise, and the listing awaits it. */
const settle = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('DisclosureGroup', () => {
    let window: TestWindow
    let root: HTMLElement

    beforeEach(() => {
        ({ window, root } = open(listingMarkup({ collapsible: true })))
        new DisclosureGroup(new Contract(root)).start()
    })

    const toggle = (rank: number) => nth(root, Contract.selector('toggle'), rank)
    const panel = (rank: number) => find(root, `#${toggle(rank).getAttribute('aria-controls')}`)
    const isOpen = (rank: number) => toggle(rank).getAttribute('aria-expanded') === 'true' && !panel(rank).hidden
    const leave = (from: Element, to: Element | null) => {
        from.dispatchEvent(new window.FocusEvent('focusout', { bubbles: true, relatedTarget: to }))
    }

    it('opens a closed panel and closes it again', () => {
        assert.equal(isOpen(0), false)

        click(window, toggle(0))
        assert.equal(isOpen(0), true)

        click(window, toggle(0))
        assert.equal(isOpen(0), false)
        assert.equal(panel(0).hidden, true)
    })

    it('opens the panel from a click on the label inside its trigger', () => {
        click(window, find(toggle(0), 'span'))

        assert.equal(isOpen(0), true)
    })

    it('keeps one panel open at a time', () => {
        click(window, toggle(0))
        click(window, toggle(1))

        assert.equal(isOpen(0), false)
        assert.equal(isOpen(1), true)
    })

    /** Q-1: in the modal drawer, the same markup is a set of sections that open on their own. */
    it('lets every section open on its own inside a modal container', () => {
        root.setAttribute('aria-modal', 'true')

        click(window, toggle(0))
        click(window, toggle(1))

        assert.equal(isOpen(0), true)
        assert.equal(isOpen(1), true)
    })

    it('closes on Escape from inside the panel and hands the focus back to its trigger', () => {
        const box = find<HTMLInputElement>(panel(0), 'input')
        click(window, toggle(0))
        box.focus()

        press(window, box, 'Escape')

        assert.equal(isOpen(0), false)
        assert.equal(window.document.activeElement, toggle(0))
    })

    it('closes on Escape only', () => {
        click(window, toggle(0))

        press(window, find(panel(0), 'input'), 'Tab')

        assert.equal(isOpen(0), true)
    })

    /** A trigger that names no panel would announce itself expanded over nothing. */
    it('leaves a trigger collapsed when the panel it names is missing', () => {
        toggle(0).setAttribute('aria-controls', 'nowhere')

        click(window, toggle(0))

        assert.equal(toggle(0).getAttribute('aria-expanded'), 'false')
    })

    it('leaves an Escape pressed elsewhere alone', () => {
        click(window, toggle(0))

        press(window, find(root, Contract.selector('sort-trigger')), 'Escape')

        assert.equal(isOpen(0), true)
    })

    it('closes on a click outside, and not on a click inside its panel', () => {
        click(window, toggle(0))

        click(window, find(panel(0), 'ul'))
        assert.equal(isOpen(0), true)

        click(window, window.document.body)
        assert.equal(isOpen(0), false)
    })

    it('closes when the focus moves on, and not while it moves between trigger and panel', () => {
        click(window, toggle(0))
        const box = find(panel(0), 'input')

        leave(toggle(0), box)
        leave(box, toggle(0))
        assert.equal(isOpen(0), true)

        leave(toggle(0), find(root, Contract.selector('sort-trigger')))
        assert.equal(isOpen(0), false)
    })

    /** The window losing focus, or a focused box hidden by a repaint, is not the visitor moving on. */
    it('stays open when the focus goes nowhere', () => {
        click(window, toggle(0))

        leave(find(panel(0), 'input'), null)

        assert.equal(isOpen(0), true)
    })

    /** UX-2: a panel that would cross the right edge of the viewport hangs from the end of its trigger. */
    it('aligns a panel on the end of its trigger only where it would overflow', () => {
        Object.defineProperty(window.document.documentElement, 'clientWidth', { value: 900, configurable: true })
        let right = 950
        panel(1).getBoundingClientRect = () => ({ right }) as DOMRect

        click(window, toggle(1))
        assert.ok(panel(1).hasAttribute('data-align-end'))

        click(window, toggle(1))
        right = 400
        click(window, toggle(1))
        assert.equal(panel(1).hasAttribute('data-align-end'), false)
    })
})

/** UX-1: ticking several values, with the grid repainted after each, leaves the panel and the focus alone. */
describe('a panel open while the listing searches at every tick', () => {
    it('stays open with the focus on the last box ticked', async () => {
        const { window, root } = open(listingMarkup({ collapsible: true }))
        const client = new FakeClient()
        const listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client, history: new FakeHistory() })
        new ListingBinding(new Contract(root), listing, description).start()
        const trigger = nth(root, Contract.selector('toggle'), 0)
        const panel = find(root, `#${trigger.getAttribute('aria-controls')}`)

        click(window, trigger)

        for (const value of ['acme', 'globex']) {
            const box = find<HTMLInputElement>(panel, `input[value="${value}"]`)
            box.focus()
            tick(window, box)
            await settle()
        }

        assert.equal(client.plans.length, 2)
        assert.equal(trigger.getAttribute('aria-expanded'), 'true')
        assert.equal(panel.hidden, false)
        assert.equal(window.document.activeElement, find(panel, 'input[value="globex"]'))
    })
})
