import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { ANNOUNCE_DELAY_MS } from '../../resources/assets/ts/shared/debounced-announcer.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { RESULTS } from '../../resources/assets/ts/shared/plan.ts'
import { click, find, listingMarkup, nextTurn, open, tick } from './dom.ts'
import { connection, described, FakeClient, FakeHistory, served } from './fixtures.ts'
import { typeInto } from './site-search-fixtures.ts'

import type { TestContext } from 'node:test'
import type { StateChanges } from '../../resources/assets/ts/listing/listing-state.ts'
import type { ListingDescription } from '../../resources/assets/ts/shared/description.ts'

const facets = [{ taxonomy: 'product_brand', multiple: true, cap: 30, visible: 10, labels: { acme: 'Acme' }, counts: {} }]

const bound = (t: TestContext, { apply = 'submit', term = '', state = {} }: { apply?: ListingDescription['apply'], term?: string, state?: StateChanges } = {}) => {
    t.mock.timers.enable({ apis: ['setTimeout'] })

    const description = described({ facets, params: { product_brand: 'brand' }, apply, state: served(state) })
    const { window, root } = open(listingMarkup({ search: term }))
    const client = new FakeClient()
    const history = new FakeHistory()
    const listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client, history })

    new ListingBinding(new Contract(root), listing, description).start()

    const hook = <E extends Element = HTMLElement>(name: string) => find<E>(root, Contract.selector(name))
    const input = hook<HTMLInputElement>('listing-search-input')
    const submit = () => {
        const event = new window.Event('submit', { bubbles: true, cancelable: true })

        hook('listing-search').dispatchEvent(event)

        return event
    }
    const searched = () => client.plans.map((plan) => plan[RESULTS]?.q)

    return { window, root, client, history, listing, hook, input, submit, searched, type: (value: string) => typeInto(window, input, value) }
}

describe('ListingSearch, submit', () => {
    it('holds a typed term in the state and searches nothing', (t) => {
        const { listing, type, searched } = bound(t)

        type('serum')
        t.mock.timers.tick(ANNOUNCE_DELAY_MS)

        assert.equal(listing.state.query, 'serum')
        assert.deepEqual(searched(), [])
    })

    it('searches on Enter, back on the first page, and writes q in the address', async (t) => {
        const { submit, type, searched, history } = bound(t, { state: { page: 3 } })

        type('ser')
        const event = submit()
        await nextTurn()

        assert.equal(event.defaultPrevented, true)
        assert.deepEqual(searched(), ['ser'])
        assert.deepEqual(history.replaced, ['/shop?q=ser'])
    })

    it('searches no term for one holding no letter and no figure (R-159)', async (t) => {
        const { submit, type, searched, listing } = bound(t)

        type('?(')
        submit()
        await nextTurn()

        assert.equal(listing.state.query, '')
        assert.deepEqual(searched(), [''])
    })

    it('searches the typed term with « Apply », with the boxes ticked meanwhile', async (t) => {
        const { window, root, hook, type, client } = bound(t)

        type('ser')
        tick(window, find<HTMLInputElement>(root, 'input[value="acme"]'))
        click(window, hook('apply'))
        await nextTurn()

        assert.equal(client.plans.length, 1)
        assert.equal(client.plans[0]?.[RESULTS]?.q, 'ser')
        assert.match(client.plans[0]?.[RESULTS]?.filter ?? '', /facets\.product_brand = "acme"/)
    })
})

describe('ListingSearch, immediate', () => {
    it('searches once typing rests, as the site search does', async (t) => {
        const { type, searched } = bound(t, { apply: 'immediate' })

        type('s')
        type('se')
        t.mock.timers.tick(119)
        assert.deepEqual(searched(), [])

        t.mock.timers.tick(1)
        await nextTurn()
        assert.deepEqual(searched(), ['se'])
    })

    it('takes the term off once the field drops under the threshold', async (t) => {
        const { type, searched, listing } = bound(t, { apply: 'immediate', term: 'ser', state: { query: 'ser' } })

        type('s')
        await nextTurn()

        assert.equal(listing.state.query, '')
        assert.deepEqual(searched(), [''])
    })

    /** The site search sets the threshold for typing, not for an address: `/boutique?q=a` stays searched. */
    it('keeps a term the address carries under the threshold', async (t) => {
        const { listing, searched, input } = bound(t, { apply: 'immediate', term: 'a', state: { query: 'a' } })

        t.mock.timers.tick(ANNOUNCE_DELAY_MS)
        await nextTurn()

        assert.equal(listing.state.query, 'a')
        assert.equal(input.value, 'a')
        assert.deepEqual(searched(), [])
    })

    it('never searches again the term the page was served with', async (t) => {
        const { searched } = bound(t, { apply: 'immediate', term: 'ser', state: { query: 'ser' } })

        t.mock.timers.tick(ANNOUNCE_DELAY_MS)
        await nextTurn()

        assert.deepEqual(searched(), [])
    })

    it('searches at once on Enter, and not a second time when the delay runs out', async (t) => {
        const { type, submit, searched } = bound(t, { apply: 'immediate' })

        type('ser')
        submit()
        await nextTurn()
        t.mock.timers.tick(ANNOUNCE_DELAY_MS)
        await nextTurn()

        assert.deepEqual(searched(), ['ser'])
    })

    /**
     * The count is written at every answer; its live region, rewritten at every letter, would talk over the
     * typing. The focus is left outside the field: Safari moves it there on no click.
     */
    it('writes the count at every answer, and says it once typing rests', async (t) => {
        const { window, root, type, client, hook } = bound(t, { apply: 'immediate' })
        const written = () => hook('total').textContent
        const said = () => hook('total-status').textContent

        client.answer = { results: { hits: [], totalHits: 4 } }
        type('ser')
        t.mock.timers.tick(120)
        await nextTurn()
        assert.equal(written(), '4 items')
        assert.equal(said(), '')

        t.mock.timers.tick(ANNOUNCE_DELAY_MS)
        assert.equal(said(), '4 items')

        client.answer = { results: { hits: [], totalHits: 9 } }
        tick(window, find<HTMLInputElement>(root, 'input[value="acme"]'))
        await nextTurn()
        assert.equal(written(), '9 items')
        assert.equal(said(), '9 items')
    })
})

describe('ListingSearch, immediate, after typing', () => {
    const typedAndAnswered = async (t: TestContext) => {
        const setup = bound(t, { apply: 'immediate' })

        setup.client.answer = { results: { hits: [], totalHits: 4 } }
        setup.type('ser')
        t.mock.timers.tick(120)
        await nextTurn()

        return { ...setup, said: () => setup.hook('total-status').textContent }
    }

    it('says the count at once when Enter searches', async (t) => {
        const { type, submit, client, said } = await typedAndAnswered(t)

        client.answer = { results: { hits: [], totalHits: 7 } }
        type('serum')
        submit()
        await nextTurn()

        assert.equal(said(), '7 items')
    })

    it('says the count at once when its own button clears the term', async (t) => {
        const { window, hook, client, said } = await typedAndAnswered(t)

        client.answer = { results: { hits: [], totalHits: 9 } }
        click(window, hook('listing-search-clear'))
        await nextTurn()

        assert.equal(said(), '9 items')
    })
})

describe('ListingSearch, taking the term off', () => {
    it('shows the clear button only while the field holds something', (t) => {
        const { hook, type } = bound(t)

        assert.equal(hook('listing-search-clear').hidden, true)
        type('s')
        assert.equal(hook('listing-search-clear').hidden, false)
        type('')
        assert.equal(hook('listing-search-clear').hidden, true)
    })

    it('clears the field, searches without the term at once and gives the field the focus back', async (t) => {
        const { window, hook, input, listing, searched } = bound(t, { term: 'ser', state: { query: 'ser' } })

        click(window, hook('listing-search-clear'))
        await nextTurn()

        assert.equal(input.value, '')
        assert.equal(listing.state.query, '')
        assert.deepEqual(searched(), [''])
        assert.ok(input.ownerDocument.activeElement === input)
    })

    it('never rewrites what the visitor typed when the term it held drops', async (t) => {
        const { input, listing, type } = bound(t, { apply: 'immediate', term: 'ser', state: { query: 'ser' } })

        type('s ')
        await nextTurn()

        assert.equal(listing.state.query, '')
        assert.equal(input.value, 's ')
    })
})

for (const apply of ['submit', 'immediate'] as const) {
    /** Safari leaves the focus in the field when a button is clicked: focus cannot tell who moved the state. */
    describe(`ListingSearch, ${apply}, following the state while the focus stays in the field`, () => {
        const withTerm = { apply, term: 'ser', state: { query: 'ser', page: 3 } }

        it('empties the field and goes back to the first page when the pill of the term is removed', async (t) => {
            const { window, root, input, listing, client } = bound(t, withTerm)

            await listing.apply()
            input.focus()
            click(window, find(root, '[data-meili="active-value"][data-kind="search"]'))
            await nextTurn()

            assert.equal(input.value, '')
            assert.equal(listing.state.page, 1)
            assert.equal(client.plans.at(-1)?.[RESULTS]?.q, '')
            assert.equal(client.plans.at(-1)?.[RESULTS]?.page, 1)
        })

        it('goes back to the first page when its own button clears it', async (t) => {
            const { window, hook, listing, client } = bound(t, withTerm)

            click(window, hook('listing-search-clear'))
            await nextTurn()

            assert.equal(listing.state.page, 1)
            assert.equal(client.plans.at(-1)?.[RESULTS]?.page, 1)
        })

        it('empties the field on « Clear all »', async (t) => {
            const { window, hook, input, listing } = bound(t, withTerm)

            input.focus()
            click(window, hook('reset'))
            await nextTurn()

            assert.equal(input.value, '')
            assert.equal(listing.state.query, '')
        })

        it('follows the state going back to no term, then forward again', async (t) => {
            const { input, history, searched } = bound(t, withTerm)

            input.focus()
            history.goBackTo('products', served({}))
            await nextTurn()
            assert.equal(input.value, '')

            history.goBackTo('products', served({ query: 'ser', page: 3 }))
            await nextTurn()
            assert.equal(input.value, 'ser')
            assert.deepEqual(searched(), ['', 'ser'])
        })
    })
}
