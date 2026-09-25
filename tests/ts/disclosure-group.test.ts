import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { DisclosureGroup } from '../../resources/assets/ts/collapsible/disclosure-group.ts'
import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { facetField } from '../../resources/assets/ts/shared/description.ts'
import { FacetQuery } from '../../resources/assets/ts/facets/facet-query.ts'
import { click, clickFromKeyboard, closestHook, find, listingMarkup, nth, open, press, tick } from './dom.ts'
import { connection, described, FakeClient, FakeHistory } from './fixtures.ts'

import type { TestWindow } from './dom.ts'

const description = described({
    apply: 'immediate',
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 5, visible: 10, labels: {}, counts: { acme: 3, globex: 2 } },
        { taxonomy: 'product_cat', multiple: false, cap: 1, visible: 10, labels: {}, counts: { coats: 1 } },
    ],
    params: { product_brand: 'brand', product_cat: 'categorie' },
})

const [brand] = description.facets

if (brand === undefined) {
    throw new Error('The fixture describes no brand facet.')
}

/** A macrotask: the fake engine answers on a resolved promise, and the listing awaits it. */
const settle = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('DisclosureGroup', () => {
    let window: TestWindow
    let root: HTMLElement

    let group: DisclosureGroup
    let closings: number

    beforeEach(() => {
        ({ window, root } = open(listingMarkup({ collapsible: true }), { styled: true }))
        closings = 0
        group = new DisclosureGroup(new Contract(root), () => closings++).start()
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

    /** Q-1: where the stylesheet keeps panels in line — a narrow screen, the drawer — they are sections that open on their own. */
    it('lets every section open on its own where the panels do not float', () => {
        window.happyDOM.setViewport({ width: 390, height: 800 })

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

    it('leaves an in-line section open on a click outside and on Escape', () => {
        window.happyDOM.setViewport({ width: 390, height: 800 })
        click(window, toggle(0))

        click(window, window.document.body)
        press(window, find(panel(0), 'input'), 'Escape')

        assert.equal(isOpen(0), true)
    })

    /** R-173 (7): applied from the bar, the choice is made — the floating panel goes, like on any click outside. */
    it('closes the floating panel when « Apply » is pressed', () => {
        click(window, toggle(0))

        click(window, find(root, Contract.selector('apply')))

        assert.equal(isOpen(0), false)
    })

    it('tells once a panel it closed is out of sight, and not before', async () => {
        let out = () => {}
        const exit = new Promise<void>((resolve) => {
            out = resolve
        })
        click(window, toggle(0))
        panel(0).getAnimations = () => [{ finish: () => {}, finished: exit } as unknown as Animation]

        click(window, toggle(0))
        await settle()
        assert.equal(closings, 0)

        out()
        await settle()
        assert.equal(closings, 1)
    })

    /** R-173 (7): a drawer that has left folds its sections away unseen, for the next opening to start folded. */
    it('folds every section of a container at once, and those only', () => {
        window.happyDOM.setViewport({ width: 390, height: 800 })
        click(window, toggle(0))
        click(window, toggle(1))
        const [brand] = [panel(0)]
        let finished = 0
        brand.getAnimations = () => [{ finish: () => finished++ } as unknown as Animation]

        group.collapseWithin(closestHook(toggle(0), 'facet'))

        assert.equal(isOpen(0), false)
        assert.equal(panel(0).hidden, true)
        assert.equal(finished, 1)
        assert.equal(isOpen(1), true)
        assert.equal(closings, 1)
    })

    it('tells nothing when a container had nothing open', () => {
        group.collapseWithin(root)

        assert.equal(closings, 0)
    })

    it('closes on a click outside, and not on a click inside its panel', () => {
        click(window, toggle(0))

        click(window, find(panel(0), 'ul'))
        assert.equal(isOpen(0), true)

        click(window, window.document.body)
        assert.equal(isOpen(0), false)
    })

    /** An Escape another component consumed — the sort list, the drawer — was not meant for the panels. */
    it('leaves its panels alone on an Escape another component consumed', () => {
        click(window, toggle(0))

        const box = find<HTMLInputElement>(panel(0), 'input')
        box.addEventListener('keydown', (event) => event.preventDefault())
        press(window, box, 'Escape')
        assert.equal(isOpen(0), true)
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

    /** Records, for each style flush of a panel, whether it happened with the transition cut. */
    const watchExits = (rank: number) => {
        const cut: boolean[] = []
        panel(rank).getAnimations = () => {
            cut.push(panel(rank).hasAttribute('data-instant'))

            return []
        }

        return cut
    }
    const watchEntries = (rank: number) => {
        const durations: unknown[] = []
        panel(rank).animate = (_: Keyframe[], options: KeyframeAnimationOptions) => {
            durations.push(options.duration)

            return {} as Animation
        }

        return durations
    }

    /** ANIM-3: a floating panel enters by the stylesheet's transition, which a second click turns back. */
    it('leaves the entry of a floating panel to the stylesheet, and plays its exit on a second click', async () => {
        const entries = watchEntries(0)
        const exits = watchExits(0)

        click(window, toggle(0))
        assert.equal(isOpen(0), true)
        assert.equal(entries.length, 0)
        assert.equal(exits.length, 0)

        click(window, toggle(0))
        await settle()
        assert.equal(exits.includes(true), false)
        assert.equal(closings, 1)
    })

    /** Emil: a key never animates — Enter or Space on a pill opens and closes its panel at once. */
    it('opens and closes a floating panel at once from the keyboard', () => {
        const exits = watchExits(0)

        clickFromKeyboard(window, toggle(0))
        assert.equal(isOpen(0), true)
        assert.deepEqual(exits, [true])

        clickFromKeyboard(window, toggle(0))
        assert.equal(isOpen(0), false)
        assert.ok(exits.length > 1 && exits.every(Boolean))
        assert.equal(panel(0).hasAttribute('data-instant'), false)
        assert.equal(closings, 1)
    })

    /** ANIM-4: a keyboard close is instant — no exit to sit through before the next key. */
    it('closes at once, its transition cut, on Escape', () => {
        click(window, toggle(0))
        const exits = watchExits(0)

        press(window, find(panel(0), 'input'), 'Escape')

        assert.equal(isOpen(0), false)
        assert.ok(exits.length > 0 && exits.every(Boolean))
        assert.equal(panel(0).hasAttribute('data-instant'), false)
        assert.equal(closings, 1)
    })

    it('closes at once when Tab takes the focus out', () => {
        click(window, toggle(0))
        const exits = watchExits(0)

        leave(find(panel(0), 'input'), find(root, Contract.selector('sort-trigger')))

        assert.equal(isOpen(0), false)
        assert.ok(exits.length > 0 && exits.every(Boolean))
        assert.equal(closings, 1)
    })

    it('goes from pill to pill with neither exit nor entry', () => {
        click(window, toggle(0))
        const exits = watchExits(0)
        const entries = watchEntries(1)

        click(window, toggle(1))

        assert.equal(isOpen(0), false)
        assert.equal(isOpen(1), true)
        assert.ok(exits.length > 0 && exits.every(Boolean))
        assert.deepEqual(entries, [])
        assert.equal(panel(1).hasAttribute('data-instant'), false)
    })

    /** A press moves the focus before its click: the click, not the focus, decides — and a click out plays the exit. */
    it('leaves a press elsewhere to its click, which plays the exit', async () => {
        click(window, toggle(0))
        const exits = watchExits(0)

        window.document.body.dispatchEvent(new window.PointerEvent('pointerdown', { bubbles: true }))
        leave(toggle(0), find(root, Contract.selector('sort-trigger')))
        assert.equal(isOpen(0), true)

        click(window, window.document.body)
        await settle()

        assert.equal(isOpen(0), false)
        assert.equal(exits.includes(true), false)
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
        const brands = { acme: 3, globex: 2 }
        client.answer = {
            results: { hits: [], totalHits: 0, facetDistribution: { [facetField(brand)]: brands } },
            [FacetQuery.keyFor(brand.taxonomy)]: { hits: [], facetDistribution: { [facetField(brand)]: brands } },
        }
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
        assert.ok(window.document.activeElement === find(panel, 'input[value="globex"]'))
    })
})

/** R-173: a value that falls to no result stays where it was, announced unavailable, and keeps the focus. */
describe('a value out of reach in an open panel', () => {
    const start = () => {
        const { window, root } = open(listingMarkup({ collapsible: true }))
        const client = new FakeClient()
        const brands = { acme: 3 }
        client.answer = {
            results: { hits: [], totalHits: 0, facetDistribution: { [facetField(brand)]: brands } },
            [FacetQuery.keyFor(brand.taxonomy)]: { hits: [], facetDistribution: { [facetField(brand)]: brands } },
        }
        const listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client, history: new FakeHistory() })
        new ListingBinding(new Contract(root), listing, description).start()
        click(window, nth(root, Contract.selector('toggle'), 0))

        return { window, client, box: (value: string) => find<HTMLInputElement>(root, `input[value="${value}"]`) }
    }

    it('keeps the focus of the box that fell to no result', async () => {
        const { window, box } = start()

        box('globex').focus()
        tick(window, box('acme'))
        await settle()

        assert.equal(box('globex').getAttribute('aria-disabled'), 'true')
        assert.ok(window.document.activeElement === box('globex'))
    })

    it('cancels a click on it: the box stays unticked and nothing is searched', async () => {
        const { window, client, box } = start()

        tick(window, box('acme'))
        await settle()
        box('acme').checked = false
        const searched = client.plans.length
        let changes = 0
        box('globex').addEventListener('change', () => changes++)

        box('globex').click()
        await settle()

        assert.equal(box('globex').checked, false)
        assert.equal(changes, 0)
        assert.equal(client.plans.length, searched)
    })

    it('refuses a `change` that would tick it', async () => {
        const { window, client, box } = start()

        tick(window, box('acme'))
        await settle()
        const searched = client.plans.length

        tick(window, box('globex'))
        await settle()

        assert.equal(box('globex').checked, false)
        assert.equal(client.plans.length, searched)
    })

    it('always lets a ticked value go', async () => {
        const { window, client, box } = start()

        tick(window, box('acme'))
        await settle()
        const searched = client.plans.length

        box('acme').click()
        await settle()

        assert.equal(box('acme').checked, false)
        assert.equal(client.plans.length, searched + 1)
    })
})

/** 4d-2: « Show more » inside a panel unfolds in place, and the panel opens folded the next time. */
describe('« Show more » inside a panel', () => {
    const folding = described({ ...description, facets: description.facets.map((facet) => ({ ...facet, visible: 1 })) })

    const start = () => {
        const { window, root } = open(listingMarkup({ collapsible: true }), { styled: true })
        const listing = new Listing(folding, connection, { filterQueries: filterQueriesOf(folding), client: new FakeClient(), history: new FakeHistory() })
        new ListingBinding(new Contract(root), listing, folding).start()
        const trigger = nth(root, Contract.selector('toggle'), 0)
        const panel = find(root, `#${trigger.getAttribute('aria-controls')}`)
        const more = find<HTMLButtonElement>(panel, Contract.selector('more'))
        const globex = closestHook(find(panel, 'input[value="globex"]'), 'facet-value')

        click(window, trigger)

        return { window, trigger, panel, more, globex }
    }

    it('unfolds in the open panel and keeps the focus on the button', () => {
        const { window, trigger, panel, more, globex } = start()

        more.focus()
        click(window, more)

        assert.equal(trigger.getAttribute('aria-expanded'), 'true')
        assert.equal(panel.hidden, false)
        assert.equal(globex.hidden, false)
        assert.equal(more.getAttribute('aria-expanded'), 'true')
        assert.ok(window.document.activeElement === more)

        click(window, more)

        assert.equal(globex.hidden, true)
        assert.equal(more.getAttribute('aria-expanded'), 'false')
        assert.ok(window.document.activeElement === more)
    })

    it('folds back once the panel has closed on Escape', () => {
        const { window, trigger, more, globex } = start()

        click(window, more)
        press(window, more, 'Escape')

        assert.equal(trigger.getAttribute('aria-expanded'), 'false')
        assert.equal(globex.hidden, true)
        assert.equal(more.getAttribute('aria-expanded'), 'false')
    })

    it('folds back once the panel has left on a second click of its trigger', async () => {
        const { window, trigger, more, globex } = start()

        click(window, more)
        click(window, trigger)
        await settle()

        assert.equal(globex.hidden, true)

        click(window, trigger)

        assert.equal(globex.hidden, true)
        assert.equal(more.hidden, false)
        assert.equal(more.getAttribute('aria-expanded'), 'false')
    })
})
