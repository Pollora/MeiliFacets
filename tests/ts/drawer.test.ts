import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { DisclosureGroup } from '../../resources/assets/ts/collapsible/disclosure-group.ts'
import { Drawer } from '../../resources/assets/ts/drawer/drawer.ts'
import { REDUCED_MOTION } from '../../resources/assets/ts/drawer/drawer-gesture.ts'
import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { click, find, listingMarkup, nth, open, press, tick } from './dom.ts'
import { connection, described, FakeClient, FakeHistory } from './fixtures.ts'

import type { TestWindow } from './dom.ts'

const FACETS = '    <div class="meilifacetsFacets"'
const FACETS_END = '<button type="button" data-meili="apply">Apply filters</button>\n    </div>'

/** Mirrors `drawer-opener.blade.php` and `drawer.blade.php` around the facets of the listing fixture. */
const drawerMarkup = ({ close = true } = {}) => `
<header id="header"><a href="/">Home</a></header>
<main id="main">${listingMarkup({ collapsible: true })
        .replace(FACETS, `
    <button type="button" aria-expanded="false" aria-controls="drawer" aria-describedby="drawer-count" data-meili="drawer-open">
        Filters <span id="drawer-count" aria-hidden="true" hidden data-meili="active-count"></span>
    </button>
    <div class="meilifacetsDrawer" id="drawer" data-media="(width < 48em)" data-meili="drawer">
        <div class="meilifacetsDrawerSheet">
            <div class="meilifacetsDrawerHead">
                <h2 id="drawer-title" tabindex="-1" data-meili="drawer-title">Filters</h2>
                ${close ? '<button type="button" aria-label="Close the filters" data-meili="drawer-close">✕</button>' : ''}
            </div>
            ${close ? '<div class="meilifacetsDrawerHandle" aria-hidden="true" data-meili="drawer-close"></div>' : ''}
            <div class="meilifacetsDrawerBody">
${FACETS}`)
        .replace(FACETS_END, `${FACETS_END}
            </div>
        </div>
    </div>`)}</main>
<footer id="footer"></footer>`

/** A media query list the test moves across the threshold. */
class FakeMedia extends EventTarget {
    matches = true

    cross(matches: boolean) {
        this.matches = matches
        this.dispatchEvent(new Event('change'))
    }
}

const description = described({
    name: 'products',
    perPage: 10,
    reachableHits: 1000,
    filter: 'post_type = "product"',
    apply: 'submit',
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

describe('Drawer', () => {
    let window: TestWindow
    let root: HTMLElement
    let media: FakeMedia
    let reduced: FakeMedia

    const drawer = () => find(root, Contract.selector('drawer'))
    const opener = () => find(root, Contract.selector('drawer-open'))
    const title = () => find(root, Contract.selector('drawer-title'))
    const inert = () => [...window.document.querySelectorAll('[inert]')].map((node) => node.id || node.getAttribute('data-meili') || node.tagName)
    const pageInert = () => inert().filter((name) => name !== drawer().id)
    const isModal = () => drawer().getAttribute('aria-modal') === 'true'

    const start = (markup = drawerMarkup()) => {
        ({ window, root } = open(markup))
        media = new FakeMedia()
        reduced = new FakeMedia()
        reduced.matches = false
        window.matchMedia = ((query: string) => (query === REDUCED_MOTION ? reduced : media)) as unknown as typeof window.matchMedia
        window.ResizeObserver = class {
            observe() {}
            disconnect() {}
            unobserve() {}
        }
        const contract = new Contract(root)
        const group = new DisclosureGroup(contract).start()
        new Drawer(contract, drawer(), { hidden: (left) => group.collapseWithin(left) }).start()
    }

    /** The drawer's own transitions, held until the test lets them end. */
    const holdExit = () => {
        let end = () => {}
        const finished = new Promise<void>((resolve) => {
            end = resolve
        })
        drawer().getAnimations = () => [{ finished } as unknown as Animation]

        return () => end()
    }
    const sectionOpen = (rank: number) => nth(root, Contract.selector('toggle'), rank).getAttribute('aria-expanded') === 'true'
    const settle = () => new Promise((resolve) => setTimeout(resolve, 0))

    beforeEach(() => start())

    it('promotes the container to a modal dialog named by its title', () => {
        click(window, opener())

        assert.equal(drawer().getAttribute('role'), 'dialog')
        assert.equal(isModal(), true)
        assert.equal(drawer().getAttribute('aria-labelledby'), title().id)
        assert.equal(opener().getAttribute('aria-expanded'), 'true')
    })

    it('moves the focus onto its title', () => {
        click(window, opener())

        assert.equal(window.document.activeElement, title())
    })

    it('makes the rest of the page inert, and only the rest', () => {
        click(window, opener())

        assert.ok(inert().includes('header'))
        assert.ok(inert().includes('footer'))
        assert.ok(inert().includes('drawer-open'))
        assert.equal(drawer().closest('[inert]'), null)
    })

    it('stays a plain container above the threshold', () => {
        media.matches = false

        click(window, opener())

        assert.equal(drawer().hasAttribute('role'), false)
        assert.equal(drawer().hasAttribute('aria-modal'), false)
        assert.deepEqual(inert(), [])
    })

    it('closes on Escape at once, hands the focus back and takes everything off', () => {
        click(window, opener())

        press(window, title(), 'Escape')

        assert.equal(isModal(), false)
        assert.equal(drawer().hasAttribute('role'), false)
        assert.equal(drawer().hasAttribute('aria-labelledby'), false)
        assert.equal(drawer().hasAttribute('data-instant'), true)
        assert.equal(opener().getAttribute('aria-expanded'), 'false')
        assert.deepEqual(pageInert(), [])
        assert.ok(window.document.activeElement === opener())
    })

    it('leaves other keys alone', () => {
        click(window, opener())

        press(window, title(), 'Tab')

        assert.equal(isModal(), true)
    })

    it('closes from its close button, animated', () => {
        click(window, opener())

        click(window, find(root, 'button' + Contract.selector('drawer-close')))

        assert.equal(isModal(), false)
        assert.equal(drawer().hasAttribute('data-instant'), false)
        assert.deepEqual(pageInert(), [])
        assert.ok(window.document.activeElement === opener())
    })

    /** R-176: a Tab pressed while the sheet is still on its way out never reaches into it. */
    it('is inert while it plays its way out, with the focus already on its opener', async () => {
        click(window, opener())
        const out = holdExit()

        click(window, find(root, 'button' + Contract.selector('drawer-close')))

        assert.ok(drawer().hasAttribute('inert'))
        assert.ok(drawer().hasAttribute('data-closing'))
        assert.ok(window.document.activeElement === opener())

        out()
        await settle()

        assert.equal(drawer().hasAttribute('inert'), false)
    })

    it('is inert on Escape too, and no longer once it opens again', () => {
        click(window, opener())
        holdExit()
        press(window, title(), 'Escape')
        assert.ok(drawer().hasAttribute('inert'))

        click(window, opener())

        assert.equal(drawer().hasAttribute('inert'), false)
        assert.ok(window.document.activeElement === title())
    })

    it('closes, animated, on a tap on its handle', () => {
        click(window, opener())

        click(window, find(drawer(), '.meilifacetsDrawerHandle'))

        assert.equal(isModal(), false)
        assert.equal(drawer().hasAttribute('data-instant'), false)
    })

    /** C-1: once applied, the visitor wants the grid. */
    it('closes when « Apply » is pressed inside it', () => {
        click(window, opener())

        click(window, find(drawer(), Contract.selector('apply')))

        assert.equal(isModal(), false)
    })

    it('closes on a click on the scrim, and not on a click inside the sheet', () => {
        click(window, opener())

        click(window, find(drawer(), '.meilifacetsDrawerBody'))
        assert.equal(isModal(), true)

        click(window, drawer())
        assert.equal(isModal(), false)
    })

    it('animates again the next time it opens', () => {
        click(window, opener())
        press(window, title(), 'Escape')

        click(window, opener())

        assert.equal(drawer().hasAttribute('data-instant'), false)
    })

    /** Q-1: sections, not dropdowns, side by side. R-173 (7): folded once the drawer has left, never while it leaves. */
    it('folds its open sections only once its way out has played', async () => {
        click(window, opener())
        click(window, nth(root, Contract.selector('toggle'), 0))
        click(window, nth(root, Contract.selector('toggle'), 1))
        const out = holdExit()

        click(window, drawer())
        await settle()

        assert.equal(drawer().hasAttribute('data-closing'), true)
        assert.equal(sectionOpen(0), true)
        assert.equal(sectionOpen(1), true)

        out()
        await settle()

        assert.equal(drawer().hasAttribute('data-closing'), false)
        assert.equal(sectionOpen(0), false)
        assert.equal(sectionOpen(1), false)
        assert.equal(window.document.activeElement, opener())
    })

    it('folds its sections at once when Escape closes it from inside one, and keeps the focus on its opener', async () => {
        click(window, opener())
        click(window, nth(root, Contract.selector('toggle'), 0))

        press(window, find(drawer(), 'input'), 'Escape')
        await settle()

        assert.equal(isModal(), false)
        assert.equal(sectionOpen(0), false)
        assert.equal(window.document.activeElement, opener())
    })

    it('keeps its sections when it opens again before its way out has played', async () => {
        click(window, opener())
        click(window, nth(root, Contract.selector('toggle'), 0))
        const out = holdExit()
        click(window, drawer())

        click(window, opener())
        out()
        await settle()

        assert.equal(isModal(), true)
        assert.equal(drawer().hasAttribute('data-closing'), false)
        assert.equal(sectionOpen(0), true)
    })

    it('waits for the last way out when closed twice in a row', async () => {
        click(window, opener())
        click(window, nth(root, Contract.selector('toggle'), 0))
        const first = holdExit()
        click(window, drawer())
        click(window, opener())
        const second = holdExit()
        click(window, drawer())

        first()
        await settle()
        assert.equal(drawer().hasAttribute('data-closing'), true)

        second()
        await settle()
        assert.equal(drawer().hasAttribute('data-closing'), false)
        assert.equal(sectionOpen(0), false)
    })

    it('leaves open a drawer whose Escape a control inside consumed', () => {
        click(window, opener())
        const inside = find(drawer(), 'input')
        inside.addEventListener('keydown', (event) => event.preventDefault())

        press(window, inside, 'Escape')

        assert.equal(isModal(), true)
    })

    it('closes and cleans up when the window grows past the threshold, leaving the focus where it is', () => {
        click(window, opener())
        const box = find<HTMLInputElement>(drawer(), 'input')
        box.focus()

        media.cross(false)

        assert.equal(isModal(), false)
        assert.equal(drawer().hasAttribute('role'), false)
        assert.equal(opener().getAttribute('aria-expanded'), 'false')
        assert.deepEqual(inert(), [])
        assert.equal(window.document.activeElement, box)
    })

    describe('its inline height', () => {
        const sheet = () => find(drawer(), '.meilifacetsDrawerSheet')

        it('is written while it is an open sheet', () => {
            click(window, opener())

            assert.notEqual(sheet().style.height, '')
        })

        it('goes once the way out has played, not before', async () => {
            click(window, opener())
            const out = holdExit()

            click(window, find(root, 'button' + Contract.selector('drawer-close')))
            assert.notEqual(sheet().style.height, '')

            out()
            await settle()
            assert.equal(sheet().style.height, '')
        })

        it('goes when the window grows past the threshold, open or leaving', () => {
            click(window, opener())
            media.cross(false)
            assert.equal(sheet().style.height, '')

            media.cross(true)
            click(window, opener())
            holdExit()
            click(window, find(root, 'button' + Contract.selector('drawer-close')))
            media.cross(false)
            assert.equal(sheet().style.height, '')
        })

        it('is never written above the threshold', () => {
            media.matches = false

            click(window, opener())

            assert.equal(sheet().style.height, '')
        })

        it('is measured again on every opening, never carried over', () => {
            Object.defineProperty(find(drawer(), '.meilifacetsDrawerBody'), 'scrollHeight', { value: 321, configurable: true })
            sheet().style.height = '800px'

            click(window, opener())

            assert.equal(sheet().style.height, '321px')
        })
    })

    it('ignores the threshold while closed', () => {
        media.cross(false)
        media.cross(true)

        assert.equal(isModal(), false)
    })

    it('ignores a click on a closed drawer', () => {
        click(window, drawer())

        assert.equal(isModal(), false)
    })
})

describe('the drawer contract', () => {
    it('requires a title and a close button in the drawer', () => {
        const { root } = open(drawerMarkup({ close: false }))

        assert.deepEqual(new Contract(root).breaches(), ['drawer > drawer-close'])
    })

    it('is met by the drawer the module renders', () => {
        const { root } = open(drawerMarkup())

        assert.deepEqual(new Contract(root).breaches(), [])
    })
})

/** The sort placed in the drawer: its list is the first thing Escape closes, and a pick stays inside. */
describe('a sort inside the drawer', () => {
    it('closes the sort list on the first Escape and the drawer on the second', () => {
        const { window, root } = open(drawerMarkup())
        window.matchMedia = (() => Object.assign(new EventTarget(), { matches: true })) as unknown as typeof window.matchMedia
        find(root, '.meilifacetsDrawerBody').append(find(root, Contract.selector('sort')))
        const client = new FakeClient()
        const listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client, history: new FakeHistory() })
        new ListingBinding(new Contract(root), listing, description).start()
        const drawer = find(root, Contract.selector('drawer'))
        const trigger = find(root, Contract.selector('sort-trigger'))

        click(window, find(root, Contract.selector('drawer-open')))
        click(window, trigger)
        assert.equal(trigger.getAttribute('aria-expanded'), 'true')
        assert.equal(trigger.closest('[inert]'), null)

        press(window, trigger, 'Escape')
        assert.equal(trigger.getAttribute('aria-expanded'), 'false')
        assert.equal(drawer.getAttribute('aria-modal'), 'true')

        press(window, trigger, 'Escape')
        assert.equal(drawer.hasAttribute('aria-modal'), false)
    })
})

/** In `submit`, the drawer holds a pending selection: ticking counts, applying searches. */
describe('a drawer over a listing that applies on submit', () => {
    it('counts on its opener what is ticked, and searches only when applied', async () => {
        const { window, root } = open(drawerMarkup())
        window.matchMedia = (() => Object.assign(new EventTarget(), { matches: true })) as unknown as typeof window.matchMedia
        const client = new FakeClient()
        const listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client, history: new FakeHistory() })
        new ListingBinding(new Contract(root), listing, description).start()
        const count = find(root, `${Contract.selector('drawer-open')} ${Contract.selector('active-count')}`)

        click(window, find(root, Contract.selector('drawer-open')))
        tick(window, find<HTMLInputElement>(root, 'input[value="acme"]'))
        tick(window, find<HTMLInputElement>(root, 'input[value="globex"]'))

        assert.equal(client.plans.length, 0)
        assert.equal(count.textContent, '2')
        assert.equal(count.hidden, false)

        click(window, find(root, Contract.selector('apply')))
        await new Promise((resolve) => setTimeout(resolve, 0))

        assert.equal(client.plans.length, 1)
    })
})

const APPLY = '<button type="button" data-meili="apply">Apply filters</button>'
const DRAWER_RESET = '<button type="button" aria-label="Clear all" hidden data-shape="icon" data-meili="reset"></button>'

/** A listing bound under its drawer, the drawer's foot holding a reset beside « Apply ». */
const boundUnderDrawer = (apply: 'submit' | 'immediate') => {
    const { window, root } = open(drawerMarkup().replace(APPLY, `${DRAWER_RESET}${APPLY}`))
    window.matchMedia = (() => Object.assign(new EventTarget(), { matches: true })) as unknown as typeof window.matchMedia
    const client = new FakeClient()
    const described_ = { ...description, apply }
    const listing = new Listing(described_, connection, { filterQueries: filterQueriesOf(described_), client, history: new FakeHistory() })
    new ListingBinding(new Contract(root), listing, described_).start()
    const drawer = find(root, Contract.selector('drawer'))

    return {
        window,
        root,
        client,
        drawer,
        box: (value: string) => find<HTMLInputElement>(root, `input[value="${value}"]`),
        cards: () => [...root.querySelectorAll('[data-meili="card"] [data-meili="title"]')].map((title) => title.textContent),
    }
}

const wait = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms))
const hit = (title: string) => ({ card: { title, url: `https://example.test/${title}` } })

const outsideReset = (root: Element, drawer: Element) => {
    const reset = [...root.querySelectorAll<HTMLElement>(Contract.selector('reset'))].find((node) => !drawer.contains(node))

    assert.ok(reset !== undefined)

    return reset
}

/** R-175: the reset hides once pressed; its focus lands on the next thing to do, never on `body`. */
describe('the focus of a pressed reset', () => {
    it('lands on « Apply » beside it in the sheet', () => {
        const { window, drawer, box } = boundUnderDrawer('submit')
        click(window, find(window.document, Contract.selector('drawer-open')))
        tick(window, box('acme'))
        const reset = find(drawer, Contract.selector('reset'))

        reset.focus()
        click(window, reset)

        assert.equal(reset.hidden, true)
        assert.ok(window.document.activeElement === find(drawer, Contract.selector('apply')))
    })

    it('lands on the sheet title when « Apply » is not shown', () => {
        const { window, drawer, box } = boundUnderDrawer('immediate')
        click(window, find(window.document, Contract.selector('drawer-open')))
        tick(window, box('acme'))
        find(drawer, Contract.selector('apply')).checkVisibility = () => false
        const reset = find(drawer, Contract.selector('reset'))

        reset.focus()
        click(window, reset)

        assert.ok(window.document.activeElement === find(drawer, Contract.selector('drawer-title')))
    })

    it('lands on the « Apply » the listing shows when it sits outside the drawer', () => {
        const { window, root, drawer, box } = boundUnderDrawer('submit')
        tick(window, box('acme'))
        const reset = outsideReset(root, drawer)

        reset.focus()
        click(window, reset)

        assert.ok(window.document.activeElement === find(drawer, Contract.selector('apply')))
    })

    it('lands on the listing when no « Apply » is shown, even if the click left it on `body`', () => {
        const { window, root, drawer, box } = boundUnderDrawer('immediate')
        tick(window, box('acme'))
        find(drawer, Contract.selector('apply')).checkVisibility = () => false

        click(window, outsideReset(root, drawer))

        assert.ok(window.document.activeElement === root)
        assert.equal(root.getAttribute('tabindex'), '-1')
    })

    it('stays where it is when it was elsewhere', () => {
        const { window, drawer, box } = boundUnderDrawer('submit')
        tick(window, box('acme'))
        box('globex').focus()

        click(window, find(drawer, Contract.selector('reset')))

        assert.ok(window.document.activeElement === box('globex'))
    })
})

/** R-173: the grid behind an open sheet is repainted once the sheet has gone, the drawer's counts at once. */
describe('a grid behind an open sheet', () => {
    it('waits for the sheet to go, then shows the latest answer only', async () => {
        const { window, drawer, client, box, cards } = boundUnderDrawer('immediate')
        click(window, find(window.document, Contract.selector('drawer-open')))

        client.answer = { results: { hits: [hit('First')], totalHits: 1 } }
        tick(window, box('acme'))
        await wait(0)
        client.answer = { results: { hits: [hit('Second')], totalHits: 1 } }
        tick(window, box('globex'))
        await wait(0)

        assert.deepEqual(cards(), [])
        assert.equal(find(window.document, Contract.selector('total')).textContent, '1 item')

        click(window, find(drawer, 'button' + Contract.selector('drawer-close')))
        await wait(50)

        assert.deepEqual(cards(), ['Second'])
    })

    it('is repainted at once while no sheet covers it', async () => {
        const { window, client, box, cards } = boundUnderDrawer('immediate')
        client.answer = { results: { hits: [hit('First')], totalHits: 1 } }

        tick(window, box('acme'))
        await wait(0)

        assert.deepEqual(cards(), ['First'])
    })

    it('shows an answer that came after the held one, and never the held one after it', async () => {
        const { window, drawer, client, box, cards } = boundUnderDrawer('immediate')
        click(window, find(window.document, Contract.selector('drawer-open')))
        client.answer = { results: { hits: [hit('Held')], totalHits: 1 } }
        tick(window, box('acme'))
        await wait(0)

        click(window, find(drawer, 'button' + Contract.selector('drawer-close')))
        client.answer = { results: { hits: [hit('Later')], totalHits: 1 } }
        tick(window, box('globex'))
        await wait(50)

        assert.deepEqual(cards(), ['Later'])
    })
})
